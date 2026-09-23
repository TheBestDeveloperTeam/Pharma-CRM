<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0">Products</h1>
      </div>
      <div class="col-sm-6 text-right">
        <button class="btn btn-primary" onclick="alert('Create product logic')">
          <?= \App\Helpers\AssetHelper::icon('icon-plus') ?> Add Product
        </button>
      </div>
    </div>
  </div>
</div>

<div class="content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-body">
        <div id="products-loading" class="text-center py-5">
            <?= \App\Helpers\AssetHelper::illustration('motion/svg-anim/loader-spinner', 'Loading') ?>
        </div>
        <div id="products-empty" class="text-center py-5" style="display: none;">
            <?= \App\Helpers\AssetHelper::illustration('empty-table', 'No products found', 'mb-3') ?>
            <h5>No products available</h5>
            <p class="text-muted">Start by adding a new product to your catalogue.</p>
        </div>
        <table class="table table-bordered table-striped" id="products-table" style="display: none;">
          <thead>
            <tr>
              <th>Code</th>
              <th>Name</th>
              <th>Category</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="products-tbody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    try {
        const res = await AppClient.fetchAPI('/admin/products');
        document.getElementById('products-loading').style.display = 'none';
        
        if (!res.data || res.data.length === 0) {
            document.getElementById('products-empty').style.display = 'block';
        } else {
            const tbody = document.getElementById('products-tbody');
            res.data.forEach(p => {
                tbody.innerHTML += `
                  <tr>
                    <td>${p.ref}</td>
                    <td>${p.name}</td>
                    <td>${p.category_name || '-'}</td>
                    <td><span class="badge badge-${p.is_active ? 'success' : 'secondary'}">${p.is_active ? 'Active' : 'Inactive'}</span></td>
                    <td>
                      <button class="btn btn-sm btn-info">Edit</button>
                    </td>
                  </tr>
                `;
            });
            document.getElementById('products-table').style.display = 'table';
        }
    } catch(e) {
        document.getElementById('products-loading').innerHTML = '<p class="text-danger">Failed to load products.</p>';
    }
});
</script>
