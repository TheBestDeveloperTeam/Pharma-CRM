<div class="content-header">
  <div class="container-fluid">
    <h1 class="m-0">Doctors</h1>
  </div>
</div>
<div class="content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Doctor List</h3>
        <button class="btn btn-primary float-right" onclick="openCreateModal()">Add Doctor</button>
      </div>
      <div class="card-body">
        <table class="table table-bordered table-striped" id="doctorsTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>License</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="createModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="createForm">
        <div class="modal-header">
          <h4 class="modal-title">Add Doctor</h4>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <input type="text" class="form-control mb-2" name="first_name" placeholder="First Name" required>
          <input type="text" class="form-control mb-2" name="last_name" placeholder="Last Name">
          <input type="email" class="form-control mb-2" name="email" placeholder="Email">
          <input type="text" class="form-control mb-2" name="phone" placeholder="Phone">
          <input type="text" class="form-control mb-2" name="license_no" placeholder="License Number" required>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const token = sessionStorage.getItem('jwt_token');
    try {
        const res = await fetch('/api/v1/doctors', {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await res.json();
        const tbody = document.querySelector('#doctorsTable tbody');
        data.data.forEach(d => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${d.id}</td><td>${d.first_name} ${d.last_name || ''}</td><td>${d.email || ''}</td><td>${d.phone || ''}</td><td>${d.license_no}</td><td>${d.status}</td>`;
            tbody.appendChild(tr);
        });
    } catch (e) {
        alert('Failed to load doctors');
    }
});

function openCreateModal() {
    $('#createModal').modal('show');
}

document.getElementById('createForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const token = sessionStorage.getItem('jwt_token');
    const data = {
        first_name: e.target.first_name.value,
        last_name: e.target.last_name.value,
        email: e.target.email.value,
        phone: e.target.phone.value,
        license_no: e.target.license_no.value
    };
    try {
        const res = await fetch('/api/v1/doctors', {
            method: 'POST',
            headers: { 
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            location.reload();
        } else {
            alert(result.error);
        }
    } catch(err) {
        alert('Failed to create doctor');
    }
});
</script>
