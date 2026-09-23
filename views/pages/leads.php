<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0">Leads</h1>
      </div>
    </div>
  </div>
</div>

<div class="content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-body">
        <div id="leads-loading" class="text-center py-5">
            <?= \App\Helpers\AssetHelper::illustration('motion/svg-anim/loader-spinner', 'Loading') ?>
        </div>
        <div id="leads-empty" class="text-center py-5" style="display: none;">
            <?= \App\Helpers\AssetHelper::illustration('empty-table', 'No leads found', 'mb-3') ?>
            <h5>No Leads Available</h5>
            <p class="text-muted">Start adding leads to build your pipeline.</p>
        </div>
        <table class="table table-bordered table-striped" id="leads-table" style="display: none;">
          <thead>
            <tr>
              <th>Lead Ref</th>
              <th>Name</th>
              <th>Contact</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="leads-tbody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    try {
        const res = await AppClient.fetchAPI('/admin/leads');
        document.getElementById('leads-loading').style.display = 'none';
        
        if (!res.data || res.data.length === 0) {
            document.getElementById('leads-empty').style.display = 'block';
        } else {
            const tbody = document.getElementById('leads-tbody');
            res.data.forEach(l => {
                tbody.innerHTML += `
                  <tr>
                    <td>${l.ref}</td>
                    <td>${l.name}</td>
                    <td>${l.phone || '-'}</td>
                    <td><span class="badge badge-secondary">${l.status}</span></td>
                    <td>
                      <button class="btn btn-sm btn-info">View</button>
                    </td>
                  </tr>
                `;
            });
            document.getElementById('leads-table').style.display = 'table';
        }
    } catch(e) {
        document.getElementById('leads-loading').innerHTML = '<p class="text-danger">Failed to load leads.</p>';
    }
});
</script>
