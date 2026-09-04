<?php
// suppliers.php — MangTV Supplier Management
session_start();
include 'db_connect.php';

// Include notification helpers
if (file_exists('notification_helpers.php')) {
    include 'notification_helpers.php';
    $unreadNotifCount = getUnreadCount($conn);
} else {
    $unreadNotifCount = 0;
}

// Handle form submissions
$successMessage = '';
$errorMessage = '';

// Add/Edit Supplier
if (isset($_POST['save_supplier'])) {
    $supplier_id = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 0;
    $supplier_name = $conn->real_escape_string($_POST['supplier_name']);
    $contact_person = $conn->real_escape_string($_POST['contact_person']);
    $contact_number = $conn->real_escape_string($_POST['contact_number']);
    $email = $conn->real_escape_string($_POST['email']);
    $address = $conn->real_escape_string($_POST['address']);
    $notes = $conn->real_escape_string($_POST['notes']);
    $status = $conn->real_escape_string($_POST['status'] ?? 'Active');
    
    if ($supplier_id > 0) {
        $sql = "UPDATE suppliers SET 
                supplier_name = '$supplier_name',
                contact_person = '$contact_person',
                contact_number = '$contact_number',
                email = '$email',
                address = '$address',
                notes = '$notes',
                status = '$status'
                WHERE supplier_id = $supplier_id";
    } else {
        $sql = "INSERT INTO suppliers 
                (supplier_name, contact_person, contact_number, email, address, notes, status) 
                VALUES 
                ('$supplier_name', '$contact_person', '$contact_number', '$email', '$address', '$notes', '$status')";
    }
    
    if ($conn->query($sql)) {
        $successMessage = $supplier_id > 0 ? 'Supplier updated successfully!' : 'Supplier added successfully!';
    } else {
        $errorMessage = 'Error: ' . $conn->error;
    }
}

// Delete Supplier
if (isset($_GET['delete'])) {
    $supplier_id = intval($_GET['delete']);
    if ($conn->query("DELETE FROM suppliers WHERE supplier_id = $supplier_id")) {
        $successMessage = 'Supplier deleted successfully!';
    }
}

// Fetch suppliers
$suppliers = $conn->query("SELECT * FROM suppliers ORDER BY supplier_name ASC");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>MangTV - Suppliers</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --dark-blue: #00537A;
      --yellow: #FFD35B;
      --light-blue: #A8E8F9;
      --bg-light: #F8FBFF;
      --text-dark: #2c3e50;
      --sidebar-width: 280px;
      --sidebar-collapsed: 80px;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Poppins', sans-serif;
      background: var(--bg-light);
      color: var(--text-dark);
      overflow-x: hidden;
    }

    /* ===================== SIDEBAR ===================== */
    .sidebar {
      position: fixed;
      left: 0;
      top: 0;
      height: 100vh;
      width: var(--sidebar-width);
      background: linear-gradient(180deg, var(--dark-blue) 0%, #006b99 100%);
      color: #fff;
      padding-top: 0;
      z-index: 1000;
      overflow-y: auto;
      overflow-x: hidden;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 4px 0 20px rgba(0,0,0,0.1);
    }

    .sidebar::-webkit-scrollbar { width: 6px; }
    .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.1); }
    .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 3px; }

    .sidebar.collapsed { width: var(--sidebar-collapsed); }

    .sidebar.collapsed .brand-text,
    .sidebar.collapsed .nav-text {
      opacity: 0;
      visibility: hidden;
    }

    .sidebar-header {
      padding: 1.5rem 1.25rem;
      background: rgba(0,0,0,0.1);
      border-bottom: 1px solid rgba(255,255,255,0.1);
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .brand-logo {
      width: 45px;
      height: 45px;
      background: var(--yellow);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      box-shadow: 0 4px 12px rgba(255,213,91,0.3);
    }

    .brand-logo i { font-size: 1.5rem; color: var(--dark-blue); }

    .brand-text { transition: all 0.3s; }
    .brand-text h4 { margin: 0; font-size: 1.3rem; font-weight: 700; color: var(--yellow); }
    .brand-text p  { margin: 0; font-size: 0.75rem; opacity: 0.8; }

    .sidebar-nav { padding: 1.5rem 0; }

    .nav-section-title {
      padding: 0.5rem 1.25rem;
      font-size: 0.7rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: rgba(255,255,255,0.5);
      margin-top: 1rem;
      transition: all 0.3s;
    }

    .sidebar.collapsed .nav-section-title {
      opacity: 0;
      height: 0;
      padding: 0;
      margin: 0;
    }

    .sidebar .nav-link {
      color: rgba(255,255,255,0.85);
      padding: 0.85rem 1.25rem;
      display: flex;
      gap: 1rem;
      align-items: center;
      font-weight: 500;
      font-size: 0.95rem;
      transition: all 0.3s;
      margin: 0.25rem 0.75rem;
      border-radius: 10px;
      position: relative;
      text-decoration: none;
    }

    .sidebar .nav-link i {
      font-size: 1.2rem;
      width: 24px;
      text-align: center;
      flex-shrink: 0;
    }

    .sidebar .nav-link .nav-text {
      transition: all 0.3s;
      white-space: nowrap;
    }

    .sidebar .nav-link:hover {
      background: rgba(255,255,255,0.1);
      color: var(--yellow);
      transform: translateX(5px);
    }

    .sidebar .nav-link.active {
      background: var(--yellow);
      color: var(--dark-blue);
      font-weight: 600;
      box-shadow: 0 4px 12px rgba(255,213,91,0.3);
    }

    .sidebar .nav-link.active::before {
      content: '';
      position: absolute;
      left: 0;
      top: 50%;
      transform: translateY(-50%);
      width: 4px;
      height: 70%;
      background: var(--dark-blue);
      border-radius: 0 4px 4px 0;
    }

    /* ===================== MAIN ===================== */
    main {
      margin-left: var(--sidebar-width);
      transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      min-height: 100vh;
      background: var(--bg-light);
    }

    main.expanded { margin-left: var(--sidebar-collapsed); }

    /* ===================== TOPBAR ===================== */
    .topbar {
      position: sticky;
      top: 0;
      z-index: 900;
      background: white;
      padding: 1rem 1.5rem;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
      border-bottom: 1px solid rgba(168,232,249,0.3);
    }

    .topbar-title h5 {
      margin: 0;
      color: var(--dark-blue);
      font-weight: 700;
      font-size: 1.4rem;
    }

    .topbar-title small {
      color: #6c757d;
      font-size: 0.85rem;
    }

    .toggle-btn {
      background: var(--light-blue);
      border: none;
      width: 40px;
      height: 40px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--dark-blue);
      font-size: 1.1rem;
      transition: all 0.3s;
      cursor: pointer;
    }

    .toggle-btn:hover {
      background: var(--yellow);
      transform: scale(1.05);
    }

    .btn-logout {
      background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
      color: white;
      border: none;
      padding: 0.5rem 1.25rem;
      border-radius: 10px;
      font-weight: 600;
      font-size: 0.9rem;
      transition: all 0.3s;
      text-decoration: none;
    }

    .btn-logout:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(0,83,122,0.3);
      color: white;
    }

    /* ===================== CARDS ===================== */
    .card-custom {
      background: white;
      border-radius: 16px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
      border: 1px solid rgba(168,232,249,0.2);
      overflow: hidden;
      margin-bottom: 1.5rem;
    }

    .card-header-custom {
      background: linear-gradient(135deg, var(--light-blue) 0%, rgba(168,232,249,0.3) 100%);
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid rgba(168,232,249,0.3);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .card-header-custom h6 {
      margin: 0;
      font-weight: 700;
      color: var(--dark-blue);
      font-size: 1.1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .card-body-custom { padding: 1.5rem; }

    /* ===================== TABLE ===================== */
    .table-custom { margin: 0; }

    .table-custom thead th {
      background: var(--yellow);
      color: var(--dark-blue);
      font-weight: 600;
      font-size: 0.85rem;
      padding: 1rem 0.75rem;
      border: none;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      vertical-align: middle;
    }

    .table-custom tbody tr { transition: all 0.3s; }

    .table-custom tbody tr:hover { background: rgba(168,232,249,0.1); }

    .table-custom tbody td {
      padding: 0.85rem 0.75rem;
      vertical-align: middle;
      border-bottom: 1px solid rgba(168,232,249,0.2);
      font-size: 0.9rem;
    }

    /* ===================== BADGES ===================== */
    .badge-active {
      background: #d1e7dd;
      color: #0f5132;
      padding: 0.35rem 0.75rem;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.75rem;
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
    }

    .badge-inactive {
      background: #f8d7da;
      color: #721c24;
      padding: 0.35rem 0.75rem;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.75rem;
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
    }

    /* ===================== ACTION BUTTONS ===================== */
    .btn-action {
      width: 32px;
      height: 32px;
      padding: 0;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: none;
      transition: all 0.3s;
      margin: 0 2px;
    }

    .btn-action:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .btn-edit   { background: var(--light-blue); color: var(--dark-blue); }
    .btn-delete { background: #dc3545; color: white; }

    /* ===================== ALERTS ===================== */
    .alert-custom {
      border-radius: 12px;
      border: none;
      padding: 1rem 1.25rem;
      margin-bottom: 1.5rem;
      animation: slideDown 0.3s;
    }

    @keyframes slideDown {
      from { opacity: 0; transform: translateY(-10px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ===================== BUTTONS ===================== */
    .btn-primary-custom {
      background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
      color: white;
      border: none;
      padding: 0.65rem 1.5rem;
      border-radius: 10px;
      font-weight: 600;
      transition: all 0.3s;
    }

    .btn-primary-custom:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(0,83,122,0.3);
      color: white;
    }

    /* ===================== MODAL ===================== */
    .modal-content { border-radius: 16px; border: none; overflow: hidden; }

    .modal-header {
      background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
      color: white;
      padding: 1.1rem 1.4rem;
      border: none;
    }

    .modal-header .btn-close { filter: brightness(0) invert(1); }
    .modal-body { padding: 1.5rem; }

    /* ===================== FORMS ===================== */
    .form-label {
      font-weight: 600;
      color: var(--dark-blue);
      font-size: 0.9rem;
      margin-bottom: 0.5rem;
    }

    .form-control,
    .form-select {
      border-radius: 10px;
      border: 2px solid rgba(168,232,249,0.5);
      padding: 0.65rem 1rem;
      font-size: 0.9rem;
      transition: all 0.3s;
    }

    .form-control:focus,
    .form-select:focus {
      border-color: var(--yellow);
      box-shadow: 0 0 0 0.25rem rgba(255,213,91,0.25);
      outline: none;
    }

    /* ===================== FOOTER ===================== */
    footer {
      background: white;
      padding: 1.5rem 0;
      margin-top: 3rem;
      text-align: center;
      color: #6c757d;
      border-top: 1px solid rgba(168,232,249,0.3);
      font-size: 0.9rem;
    }

    /* ===================== RESPONSIVE ===================== */
    @media (max-width: 992px) {
      .sidebar { width: var(--sidebar-collapsed); }
      .sidebar .brand-text,
      .sidebar .nav-text,
      .sidebar .nav-section-title {
        opacity: 0;
        visibility: hidden;
      }
      main { margin-left: var(--sidebar-collapsed); }
    }

    @media (max-width: 768px) {
      .topbar { padding: 1rem; }
    }
  </style>
</head>
<body>
  <nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="brand-logo"><i class="fas fa-tshirt"></i></div>
      <div class="brand-text">
        <h4>MangTV Laundry Shop</h4>
        <p>Admin Dashboard</p>
      </div>
    </div>
    <div class="sidebar-nav">
      <div class="nav-section-title">Main Menu</div>
      <ul class="nav flex-column">
        <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fa fa-chart-line"></i><span class="nav-text">Dashboard</span></a></li>
        <li class="nav-item"><a href="customer_database.php" class="nav-link"><i class="fa fa-users"></i><span class="nav-text">Customers</span></a></li>
        <li class="nav-item"><a href="digital_record.php" class="nav-link"><i class="fa fa-database"></i><span class="nav-text">Records</span></a></li>
      </ul>
      <div class="nav-section-title">Operations</div>
      <ul class="nav flex-column">
        <li class="nav-item"><a href="order_scheduling.php" class="nav-link"><i class="fa fa-calendar-check"></i><span class="nav-text">Schedules</span></a></li>
        <li class="nav-item"><a href="walkin.php" class="nav-link"><i class="fa fa-person-walking"></i><span class="nav-text">Walk-in</span></a></li>
        <li class="nav-item"><a href="payments.php" class="nav-link"><i class="fa fa-credit-card"></i><span class="nav-text">Payments</span></a></li>
        <li class="nav-item"><a href="financials.php" class="nav-link"><i class="fa fa-chart-pie"></i><span class="nav-text">Financials</span></a></li>
      </ul>
      <div class="nav-section-title">Inventory</div>
      <ul class="nav flex-column">
        <li class="nav-item"><a href="inventory.php" class="nav-link"><i class="fa fa-boxes"></i><span class="nav-text">Inventory</span></a></li>
        <li class="nav-item"><a href="suppliers.php" class="nav-link active"><i class="fa fa-truck"></i><span class="nav-text">Suppliers</span></a></li>
        <li class="nav-item"><a href="purchase_orders.php" class="nav-link"><i class="fa fa-shopping-cart"></i><span class="nav-text">Purchase Orders</span></a></li>
      </ul>
      <div class="nav-section-title">Support</div>
      <ul class="nav flex-column">
        <li class="nav-item"><a href="complaints.php" class="nav-link"><i class="fa fa-exclamation-circle"></i><span class="nav-text">Complaints</span></a></li>
        <li class="nav-item"><a href="employees.php" class="nav-link"><i class="fa fa-user-tie"></i><span class="nav-text">Employees</span></a></li>
        <li class="nav-item"><a href="feedback.php" class="nav-link"><i class="fa fa-comments"></i><span class="nav-text">Feedback</span></a></li>
      </ul>
      <div class="nav-section-title">Account</div>
      <ul class="nav flex-column">
        <li class="nav-item"><a href="logout.php" class="nav-link"><i class="fa fa-right-from-bracket"></i><span class="nav-text">Logout</span></a></li>
      </ul>
    </div>
  </nav>

  <main id="mainContent">
    <div class="topbar">
      <div class="d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
          <button class="toggle-btn" id="toggleSidebar"><i class="fa fa-bars"></i></button>
          <div class="topbar-title">
            <h5>Supplier Management</h5>
            <small>Manage your suppliers and vendors</small>
          </div>
        </div>
        <a href="logout.php" class="btn btn-logout"><i class="fa fa-right-from-bracket me-2"></i>Logout</a>
      </div>
    </div>

    <div class="container-fluid py-4 px-4">
      <?php if ($successMessage): ?>
        <div class="alert alert-success alert-custom"><i class="fa fa-check-circle me-2"></i><?= $successMessage ?></div>
      <?php endif; ?>
      <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-custom"><i class="fa fa-exclamation-circle me-2"></i><?= $errorMessage ?></div>
      <?php endif; ?>

      <div class="card-custom">
        <div class="card-header-custom">
          <h6><i class="fa fa-truck me-2"></i>Supplier List</h6>
          <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
            <i class="fa fa-plus me-2"></i>Add Supplier
          </button>
        </div>
        <div class="card-body-custom p-0">
          <div class="table-responsive">
            <table class="table table-custom">
              <thead>
                <tr>
                  <th>Supplier Name</th>
                  <th>Contact Person</th>
                  <th>Contact Number</th>
                  <th>Email</th>
                  <th>Address</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($suppliers && $suppliers->num_rows > 0): ?>
                  <?php while ($sup = $suppliers->fetch_assoc()): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($sup['supplier_name']) ?></strong></td>
                    <td><?= htmlspecialchars($sup['contact_person'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($sup['contact_number'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($sup['email'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($sup['address'] ?: '-') ?></td>
                    <td>
                      <span class="<?= $sup['status'] == 'Active' ? 'badge-active' : 'badge-inactive' ?>">
                        <i class="fa <?= $sup['status'] == 'Active' ? 'fa-check-circle' : 'fa-times-circle' ?> me-1"></i>
                        <?= $sup['status'] ?>
                      </span>
                    </td>
                    <td>
                      <button class="btn-action btn-edit" onclick="editSupplier(<?= $sup['supplier_id'] ?>)" title="Edit"><i class="fa fa-edit"></i></button>
                      <a href="?delete=<?= $sup['supplier_id'] ?>" class="btn-action btn-delete" onclick="return confirm('Delete this supplier?')" title="Delete"><i class="fa fa-trash"></i></a>
                    </td>
                  </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr><td colspan="7" class="text-center py-5 text-muted">No suppliers found</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <footer><p class="mb-0">© <?= date('Y') ?> <strong>MangTV Laundry Shop</strong> - All Rights Reserved</p></footer>
    </div>
  </main>

  <!-- Add/Edit Supplier Modal -->
  <div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST" id="supplierForm">
          <div class="modal-header">
            <h5 class="modal-title" id="modalTitle"><i class="fa fa-plus-circle me-2"></i>Add Supplier</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="supplier_id" id="edit_supplier_id">
            <div class="mb-3">
              <label class="form-label">Supplier Name *</label>
              <input type="text" name="supplier_name" id="edit_supplier_name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Contact Person</label>
              <input type="text" name="contact_person" id="edit_contact_person" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">Contact Number</label>
              <input type="text" name="contact_number" id="edit_contact_number" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" name="email" id="edit_email" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">Address</label>
              <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Status</label>
              <select name="status" id="edit_status" class="form-select">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="save_supplier" class="btn btn-primary-custom"><i class="fa fa-save me-2"></i>Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.getElementById('toggleSidebar').addEventListener('click', () => {
      document.getElementById('sidebar').classList.toggle('collapsed');
      document.getElementById('mainContent').classList.toggle('expanded');
    });

    function editSupplier(id) {
      fetch('get_supplier.php?id=' + id)
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            const s = data.supplier;
            document.getElementById('edit_supplier_id').value = s.supplier_id;
            document.getElementById('edit_supplier_name').value = s.supplier_name;
            document.getElementById('edit_contact_person').value = s.contact_person || '';
            document.getElementById('edit_contact_number').value = s.contact_number || '';
            document.getElementById('edit_email').value = s.email || '';
            document.getElementById('edit_address').value = s.address || '';
            document.getElementById('edit_notes').value = s.notes || '';
            document.getElementById('edit_status').value = s.status;
            document.getElementById('modalTitle').innerHTML = '<i class="fa fa-edit me-2"></i>Edit Supplier';
            new bootstrap.Modal(document.getElementById('addSupplierModal')).show();
          }
        });
    }
  </script>
</body>
</html>
<?php $conn->close(); ?>