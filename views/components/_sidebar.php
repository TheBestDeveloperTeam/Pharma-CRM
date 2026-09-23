<aside class="main-sidebar sidebar-dark-primary elevation-4">
  <a href="/" class="brand-link">
    <span class="brand-text font-weight-light">Pharma CRM</span>
  </a>

  <div class="sidebar">
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
        <li class="nav-item">
          <a href="/admin/dashboard" class="nav-link">
            <?= \App\Helpers\AssetHelper::icon('icon-chart-pie', 'nav-icon') ?>
            <p>Dashboard</p>
          </a>
        </li>
        <li class="nav-header">CRM</li>
        <li class="nav-item">
          <a href="/admin/leads" class="nav-link">
            <?= \App\Helpers\AssetHelper::icon('icon-users', 'nav-icon') ?>
            <p>Leads</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="/admin/parties" class="nav-link">
            <?= \App\Helpers\AssetHelper::icon('icon-building', 'nav-icon') ?>
            <p>Parties</p>
          </a>
        </li>
        <li class="nav-header">CATALOGUE</li>
        <li class="nav-item">
          <a href="/admin/products" class="nav-link">
            <?= \App\Helpers\AssetHelper::icon('icon-pill', 'nav-icon') ?>
            <p>Products</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="/admin/categories" class="nav-link">
            <?= \App\Helpers\AssetHelper::icon('icon-tags', 'nav-icon') ?>
            <p>Categories</p>
          </a>
        </li>
        <li class="nav-header">COMMERCE</li>
        <li class="nav-item">
          <a href="/admin/orders" class="nav-link">
            <?= \App\Helpers\AssetHelper::icon('icon-shopping-cart', 'nav-icon') ?>
            <p>Orders</p>
          </a>
        </li>
      </ul>
    </nav>
  </div>
</aside>
