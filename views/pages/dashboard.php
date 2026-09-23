<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0">Dashboard</h1>
      </div>
    </div>
  </div>
</div>

<div class="content">
  <div class="container-fluid">
    
    <div class="row" id="metrics-container" style="display: none;">
      <!-- Stats dynamically injected here -->
    </div>
    
    <div class="row mt-4">
      <div class="col-12 text-center" id="dashboard-loading">
        <?= \App\Helpers\AssetHelper::illustration('motion/svg-anim/loader-spinner', 'Loading', 'mb-3') ?>
        <p class="text-muted">Loading metrics...</p>
      </div>
    </div>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    try {
        const stats = await AppClient.fetchAPI('/super/dashboard/stats'); // Or /admin/dashboard/stats if implemented
        document.getElementById('dashboard-loading').style.display = 'none';
        
        const container = document.getElementById('metrics-container');
        
        // Build cards
        const renderCard = (title, val, iconClass, colorClass) => `
          <div class="col-lg-3 col-6">
            <div class="small-box ${colorClass}">
              <div class="inner">
                <h3>${val}</h3>
                <p>${title}</p>
              </div>
              <div class="icon">
                <i class="nav-icon ${iconClass}"></i>
              </div>
            </div>
          </div>
        `;
        
        // This assumes stats returns some basic info, adjust as needed based on actual API
        let html = '';
        if(stats.data) {
            html += renderCard('Total Orders', stats.data.orders_count || 0, 'fas fa-shopping-cart', 'bg-info');
            html += renderCard('Active Leads', stats.data.leads_count || 0, 'fas fa-users', 'bg-success');
            html += renderCard('Products', stats.data.products_count || 0, 'fas fa-pill', 'bg-warning');
        } else {
            // fallback
            html += renderCard('Welcome', '', 'fas fa-home', 'bg-info');
        }
        
        container.innerHTML = html;
        container.style.display = 'flex';
        
    } catch (e) {
        document.getElementById('dashboard-loading').innerHTML = '<p class="text-danger">Failed to load dashboard data.</p>';
    }
});
</script>
