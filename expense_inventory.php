<?php
include 'db_connect.php';

// ✅ Sanitize function
function esc($conn, $v) { return $conn->real_escape_string(trim($v)); }

// ✅ Add inventory item
if (isset($_POST['add_inventory'])) {
  $item = esc($conn, $_POST['item_name']);
  $qty = (int)$_POST['quantity'];
  $unit = esc($conn, $_POST['unit'] ?? 'pcs');
  $cost = (float)$_POST['unit_cost'];
  $cat = esc($conn, $_POST['category'] ?? '');
  $remarks = esc($conn, $_POST['remarks'] ?? '');

  if ($qty > 0 && $cost > 0 && !empty($item)) {
    $conn->query("INSERT INTO expense_inventory (item_name, quantity, unit, unit_cost, category, remarks) 
                  VALUES ('$item', $qty, '$unit', $cost, '$cat', '$remarks')");

    // Optional: Add to financial_records as expense
    $total = $qty * $cost;
    $conn->query("INSERT INTO financial_records (date, description, category, type, amount) 
                  VALUES (CURDATE(), 'Purchase of $item', '$cat', 'Expense', $total)");
  }
  header("Location: expense_inventory.php");
  exit;
}

// ✅ Delete item
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  $conn->query("DELETE FROM expense_inventory WHERE id=$id");
  header("Location: expense_inventory.php");
  exit;
}

// ✅ Fetch items
$items = $conn->query("SELECT * FROM expense_inventory ORDER BY date_added DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Expense Inventory</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<style>
body { font-family: 'Nunito', sans-serif; background-color: #f1f6fb; }
.sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 300px; background-color: #00537A; color: #fff; padding-top: 1.5rem; overflow-y: auto; transition: all 0.3s; }
.sidebar.collapsed { width: 90px; }
.sidebar .nav-link { color: #fff; font-weight: 500; padding: 0.75rem 1rem; display: flex; align-items: center; transition: all 0.2s; }
.sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: #FFD35B; color: #000; border-radius: 0.35rem; }
.sidebar .nav-link i { min-width: 30px; text-align: center; margin-right: 0.75rem; }
.sidebar.collapsed .nav-link span { display: none; }
main { margin-left: 300px; transition: margin-left 0.3s; }
main.expanded { margin-left: 90px; }
.topbar { position: sticky; top: 0; background-color: #FFD35B; padding: 0.5rem 1rem; display: flex; justify-content: space-between; align-items: center; }
.btn-toggle { border: none; background: none; font-size: 1.25rem; cursor: pointer; }
.table td, .table th { vertical-align: middle; font-size: 0.9rem; }
</style>
</head>
<body>

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
  <span class="brand fw-bold ps-3 mb-3 d-block">MangTV Admin</span>
  <ul class="nav flex-column">
    <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
    <li class="nav-item"><a class="nav-link" href="financials.php"><i class="fas fa-chart-line"></i> <span>Financials</span></a></li>
    <li class="nav-item"><a class="nav-link active" href="expense_inventory.php"><i class="fas fa-boxes"></i> <span>Expense Inventory</span></a></li>
    <li class="nav-item"><a class="nav-link" href="employees.php"><i class="fas fa-user-tie"></i> <span>Employees</span></a></li>
    <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
  </ul>
</nav>

<main id="mainContent">
  <div class="topbar">
    <div class="d-flex align-items-center gap-2">
      <button class="btn-toggle" id="toggleSidebar"><i class="fas fa-bars"></i></button>
      <h5 class="mb-0 text-dark">Expense Inventory</h5>
    </div>
  </div>

  <div class="container-fluid mt-3">
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-warning fw-bold"><i class="fas fa-plus me-1"></i> Add New Expense Item</div>
      <div class="card-body">
        <form method="post">
          <div class="row g-2">
            <div class="col-md-4"><input type="text" name="item_name" class="form-control form-control-sm" placeholder="Item Name" required></div>
            <div class="col-md-2"><input type="number" name="quantity" class="form-control form-control-sm" placeholder="Qty" required></div>
            <div class="col-md-2"><input type="text" name="unit" class="form-control form-control-sm" placeholder="Unit (e.g. pcs)"></div>
            <div class="col-md-2"><input type="number" step="0.01" name="unit_cost" class="form-control form-control-sm" placeholder="Unit Cost" required></div>
            <div class="col-md-2"><input type="text" name="category" class="form-control form-control-sm" placeholder="Category"></div>
          </div>
          <div class="mt-2">
            <input type="text" name="remarks" class="form-control form-control-sm" placeholder="Remarks (optional)">
          </div>
          <button type="submit" name="add_inventory" class="btn btn-sm btn-warning w-100 mt-2">Add to Inventory</button>
        </form>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header bg-light fw-bold"><i class="fas fa-list me-1"></i> Inventory Records</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-striped">
            <thead class="table-secondary">
              <tr>
                <th>Date</th>
                <th>Item</th>
                <th>Qty</th>
                <th>Unit</th>
                <th>Unit Cost</th>
                <th>Total Cost</th>
                <th>Category</th>
                <th>Remarks</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php
              if ($items && $items->num_rows > 0) {
                while ($inv = $items->fetch_assoc()) {
                  echo "<tr>
                          <td>{$inv['date_added']}</td>
                          <td>{$inv['item_name']}</td>
                          <td>{$inv['quantity']}</td>
                          <td>{$inv['unit']}</td>
                          <td>₱".number_format($inv['unit_cost'],2)."</td>
                          <td>₱".number_format($inv['total_cost'],2)."</td>
                          <td>{$inv['category']}</td>
                          <td>{$inv['remarks']}</td>
                          <td><a href='?delete={$inv['id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Delete this item?\")'><i class='fas fa-trash'></i></a></td>
                        </tr>";
                }
              } else {
                echo "<tr><td colspan='9' class='text-center'>No inventory items found.</td></tr>";
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const sidebar = document.getElementById('sidebar');
const mainContent = document.getElementById('mainContent');
document.getElementById('toggleSidebar').addEventListener('click', ()=>{
  sidebar.classList.toggle('collapsed');
  mainContent.classList.toggle('expanded');
});
</script>
</body>
</html>