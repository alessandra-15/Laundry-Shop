<?php
include 'db_connect.php';

// --- Handle Add Product ---
$add_msg = '';
if(isset($_POST['add_product'])){
    $p_name = $conn->real_escape_string($_POST['p_name']);
    $p_stock = (int)($_POST['p_stock'] ?? 0);
    $p_cost  = (float)($_POST['p_cost'] ?? 0);

    if($p_name == '' || $p_stock < 0 || $p_cost <= 0){
        $add_msg = "Please provide valid product info.";
    } else {
        $insert = "INSERT INTO products_inventory (product_name, stock, cost) VALUES ('$p_name', '$p_stock', '$p_cost')";
        if($conn->query($insert)){
            header("Location: products_inventory.php");
            exit;
        } else {
            $add_msg = "Error: ".$conn->error;
        }
    }
}

// --- Handle Stock Update ---
if(isset($_POST['update_stock'])){
    $pid = (int)$_POST['product_id'];
    $change = (int)$_POST['stock_change'];
    $type = $_POST['type']; // add or deduct

    $q = $conn->query("SELECT stock FROM products_inventory WHERE id=$pid");
    if($q && $q->num_rows>0){
        $row = $q->fetch_assoc();
        $new_stock = $type=='add' ? $row['stock']+$change : max(0,$row['stock']-$change);
        $conn->query("UPDATE products_inventory SET stock=$new_stock WHERE id=$pid");
        header("Location: products_inventory.php");
        exit;
    }
}

// --- Handle Delete Product ---
if(isset($_POST['delete_product'])){
    $pid = (int)$_POST['product_id'];
    $conn->query("DELETE FROM products_inventory WHERE id=$pid");
    header("Location: products_inventory.php");
    exit;
}

// --- Fetch Products ---
$productsQ = $conn->query("SELECT * FROM products_inventory ORDER BY product_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Product Inventory</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<style>
body { font-family: 'Nunito', sans-serif; background:#F8F8F8; }
.sidebar { position: fixed; top:0; left:0; width:300px; height:100vh; background:#00537A; color:#fff; padding-top:1.5rem; transition:0.3s; overflow-y:auto; z-index:1000; }
.sidebar.collapsed { width:90px; }
.sidebar .brand { font-size:1.25rem; font-weight:bold; padding:0 1rem; margin-bottom:1rem; }
.sidebar.collapsed .brand { display:none; }
.sidebar .nav-link { color:#fff; font-weight:500; padding:0.75rem 1rem; display:flex; align-items:center; transition:0.2s; }
.sidebar .nav-link i { min-width:30px; margin-right:0.75rem; }
.sidebar.collapsed .nav-link span { display:none; }
.sidebar .nav-link:hover, .sidebar .nav-link.active { background:#F2B14A; color:#000; border-radius:0.35rem; }
main { margin-left:300px; transition:0.3s; min-height:100vh; }
main.expanded { margin-left:90px; }
.topbar { position:sticky; top:0; z-index:999; background:#F2B14A; display:flex; align-items:center; justify-content:space-between; padding:0.5rem 1.5rem; box-shadow:0 2px 6px rgba(0,0,0,0.1); }
.btn-toggle { background:none; border:none; font-size:1.25rem; cursor:pointer; color:#000; }
.card { background:#fff; border:none; border-radius:0.5rem; box-shadow:0 2px 6px rgba(0,0,0,0.05); }
.small-table td, .small-table th { padding:.45rem .6rem; font-size:.9rem; vertical-align:middle; }
.btn-sm { font-size:0.8rem; }
</style>
</head>
<body>

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
<span class="brand">MangTV Admin</span>
<ul class="nav flex-column">
<li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
<li class="nav-item"><a class="nav-link" href="financials.php"><i class="fas fa-chart-line"></i> <span>Financials</span></a></li>
<li class="nav-item"><a class="nav-link active" href="products_inventory.php"><i class="fas fa-boxes"></i> <span>Product Inventory</span></a></li>
<li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
</ul>
</nav>

<main id="mainContent">
<div class="topbar">
  <button class="btn-toggle" id="toggleSidebar"><i class="fas fa-bars"></i></button>
  <h5 class="mb-0">Product Inventory</h5>
</div>

<div class="container-fluid mt-3">
<div class="row">
  <!-- Add Product Card -->
  <div class="col-md-4 mb-3">
    <div class="card p-3">
      <h6 class="fw-bold mb-3">Add New Product</h6>
      <form method="post">
        <div class="mb-2">
          <label class="form-label">Product Name</label>
          <input type="text" name="p_name" class="form-control form-control-sm" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Stock</label>
          <input type="number" name="p_stock" class="form-control form-control-sm" min="0" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Cost per Unit</label>
          <input type="number" name="p_cost" class="form-control form-control-sm" min="0.01" step="0.01" required>
        </div>
        <button type="submit" name="add_product" class="btn btn-success w-100 btn-sm">Add Product</button>
        <?php if($add_msg) echo "<p class='text-danger small mt-1'>$add_msg</p>"; ?>
      </form>
    </div>
  </div>

  <!-- Products Table -->
  <div class="col-md-8 mb-3">
    <div class="card p-3">
      <h6 class="fw-bold mb-3">Inventory List</h6>
      <div class="table-responsive">
        <table class="table table-bordered small-table">
          <thead class="table-secondary">
            <tr>
              <th>Product</th>
              <th>Stock</th>
              <th>Cost</th>
              <th>Total Value</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            if($productsQ && $productsQ->num_rows>0){
              while($row=$productsQ->fetch_assoc()){
                echo "<tr>
                        <td>{$row['product_name']}</td>
                        <td>{$row['stock']}</td>
                        <td>₱".number_format($row['cost'],2)."</td>
                        <td>₱".number_format($row['stock']*$row['cost'],2)."</td>
                        <td>
                          <form method='post' class='d-flex gap-1'>
                            <input type='hidden' name='product_id' value='{$row['id']}'>
                            <input type='number' name='stock_change' class='form-control form-control-sm' style='width:70px;' min='1' required>
                            <button type='submit' name='update_stock' value='add' class='btn btn-sm btn-success' formaction='?type=add'>+</button>
                            <button type='submit' name='update_stock' value='deduct' class='btn btn-sm btn-warning' formaction='?type=deduct'>-</button>
                            <button type='submit' name='delete_product' class='btn btn-sm btn-danger' onclick='return confirm(\"Delete product?\")'>x</button>
                          </form>
                        </td>
                      </tr>";
              }
            } else {
              echo "<tr><td colspan='5' class='text-center'>No products found.</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>

<footer class="mt-4 text-center text-muted">© <?= date('Y') ?> MangTV Admin</footer>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('toggleSidebar').addEventListener('click', ()=>{
  document.getElementById('sidebar').classList.toggle('collapsed');
  document.getElementById('mainContent').classList.toggle('expanded');
});
</script>
</body>
</html>