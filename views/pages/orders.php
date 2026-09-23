<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0">Orders</h1>
      </div>
    </div>
  </div>
</div>

<div class="content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-body">
        <div id="orders-loading" class="text-center py-5">
            <?= \App\Helpers\AssetHelper::illustration('motion/svg-anim/loader-spinner', 'Loading') ?>
        </div>
        <div id="orders-empty" class="text-center py-5" style="display: none;">
            <?= \App\Helpers\AssetHelper::illustration('empty-states/orders-empty', 'No orders found', 'mb-3') ?>
            <h5>No Orders Yet</h5>
            <p class="text-muted">You haven't received any orders.</p>
        </div>
        <table class="table table-bordered table-striped" id="orders-table" style="display: none;">
          <thead>
            <tr>
              <th>Order Ref</th>
              <th>Date</th>
              <th>Customer</th>
              <th>Status</th>
              <th>Total</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="orders-tbody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    try {
        const res = await AppClient.fetchAPI('/admin/orders');
        document.getElementById('orders-loading').style.display = 'none';
        
        if (!res.data || res.data.length === 0) {
            document.getElementById('orders-empty').style.display = 'block';
        } else {
            const tbody = document.getElementById('orders-tbody');
            res.data.forEach(o => {
                tbody.innerHTML += `
                  <tr>
                    <td>${o.ref}</td>
                    <td>${o.created_at}</td>
                    <td>${o.party_name || '-'}</td>
                    <td><span class="badge badge-info">${o.status}</span></td>
                    <td>${o.total_amount}</td>
                    <td>
                      <button class="btn btn-sm btn-primary">View</button>
                    </td>
                  </tr>
                `;
            });
            document.getElementById('orders-table').style.display = 'table';
        }
    } catch(e) {
        document.getElementById('orders-loading').innerHTML = '<p class="text-danger">Failed to load orders.</p>';
    }
});
</script>
