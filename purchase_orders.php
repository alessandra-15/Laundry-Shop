<?php
// purchase_orders.php — MangTV Purchase Order Management
session_start();
include 'db_connect.php';

// Include notification helpers
if (file_exists('notification_helpers.php')) {
    include 'notification_helpers.php';
    $unreadNotifCount = getUnreadCount($conn);
} else {
    $unreadNotifCount = 0;
}

// Get unread inventory notifications
$invNotifCount = $conn->query("SELECT COUNT(*) as cnt FROM inventory_notifications WHERE is_read = 0")->fetch_assoc()['cnt'] ?? 0;

$successMessage = '';
$errorMessage = '';

// ─────────────────────────────────────────────
// Handle success message from redirect
// ─────────────────────────────────────────────
if (isset($_GET['success']) && $_GET['success'] === 'received') {
    $successMessage = 'Purchase order received! Inventory stock has been updated.';
}

// ─────────────────────────────────────────────
// Create / Update Purchase Order
// ─────────────────────────────────────────────
if (isset($_POST['save_po'])) {
    $po_id       = isset($_POST['po_id']) ? intval($_POST['po_id']) : 0;
    $po_number   = $conn->real_escape_string($_POST['po_number']);
    $supplier_id = intval($_POST['supplier_id']);
    $order_date  = $conn->real_escape_string($_POST['order_date']);
    $expected_delivery = !empty($_POST['expected_delivery'])
        ? "'" . $conn->real_escape_string($_POST['expected_delivery']) . "'"
        : 'NULL';
    $status = $conn->real_escape_string($_POST['status']);
    $notes  = $conn->real_escape_string($_POST['notes'] ?? '');

    $conn->begin_transaction();
    try {
        if ($po_id > 0) {
            $conn->query("UPDATE purchase_orders SET
                po_number = '$po_number',
                supplier_id = $supplier_id,
                order_date = '$order_date',
                expected_delivery_date = $expected_delivery,
                status = '$status',
                notes = '$notes'
                WHERE po_id = $po_id");
        } else {
            $conn->query("INSERT INTO purchase_orders
                (po_number, supplier_id, order_date, expected_delivery_date, status, notes)
                VALUES ('$po_number', $supplier_id, '$order_date', $expected_delivery, '$status', '$notes')");
            $po_id = $conn->insert_id;
        }

        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                if (!empty($item['item_id']) && !empty($item['quantity']) && !empty($item['unit_cost'])) {
                    $item_id   = intval($item['item_id']);
                    $quantity  = floatval($item['quantity']);
                    $unit_cost = floatval($item['unit_cost']);
                    $check = $conn->query("SELECT po_item_id FROM purchase_order_items WHERE po_id = $po_id AND item_id = $item_id");
                    if ($check->num_rows > 0) {
                        $conn->query("UPDATE purchase_order_items SET quantity = $quantity, unit_cost = $unit_cost WHERE po_id = $po_id AND item_id = $item_id");
                    } else {
                        $conn->query("INSERT INTO purchase_order_items (po_id, item_id, quantity, unit_cost) VALUES ($po_id, $item_id, $quantity, $unit_cost)");
                    }
                }
            }
        }

        $conn->query("UPDATE purchase_orders SET total_amount = (
            SELECT COALESCE(SUM(quantity * unit_cost), 0)
            FROM purchase_order_items WHERE po_id = $po_id
        ) WHERE po_id = $po_id");

        $conn->commit();
        $successMessage = $po_id > 0 ? 'Purchase order updated successfully!' : 'Purchase order created successfully!';
    } catch (Exception $e) {
        $conn->rollback();
        $errorMessage = 'Error: ' . $e->getMessage();
    }
}

// ─────────────────────────────────────────────
// Receive Purchase Order
// PHP handles stock update directly (works with or without DB trigger)
// Uses correct ENUM values: 'Stock In', 'Purchase'
// ─────────────────────────────────────────────
if (isset($_POST['receive_po'])) {
    $po_id = (int)$_POST['po_id'];

    $conn->begin_transaction();
    try {
        // STEP 1 — Save each item's received_quantity AND update inventory
        if (!empty($_POST['received_qty']) && is_array($_POST['received_qty'])) {
            foreach ($_POST['received_qty'] as $poi_item_id => $qty) {
                $qty         = (float)$qty;
                $poi_item_id = (int)$poi_item_id;
                if ($qty <= 0) continue;

                // Save received_quantity on the PO item row
                $conn->query("UPDATE purchase_order_items
                              SET received_quantity = $qty
                              WHERE po_item_id = $poi_item_id AND po_id = $po_id");

                // Get current stock and item info BEFORE updating
                $row = $conn->query("
                    SELECT poi.item_id, inv.current_stock, inv.item_name, inv.unit
                    FROM purchase_order_items poi
                    JOIN inventory_items inv ON inv.item_id = poi.item_id
                    WHERE poi.po_item_id = $poi_item_id
                ")->fetch_assoc();

                if ($row) {
                    $item_id    = (int)$row['item_id'];
                    $prev_stock = (float)$row['current_stock'];
                    $new_stock  = $prev_stock + $qty;

                    // Update inventory stock
                    $conn->query("UPDATE inventory_items
                                  SET current_stock = $new_stock,
                                      last_restock_date = CURDATE()
                                  WHERE item_id = $item_id");

                    // Log the transaction — 'Stock In' and 'Purchase' are valid ENUM values
                    $po_num = $conn->real_escape_string($_POST['po_number'] ?? "PO#$po_id");
                    $conn->query("INSERT INTO inventory_transactions
                        (item_id, transaction_type, quantity, previous_stock, new_stock,
                         reference_type, reference_id, notes, transaction_date)
                        VALUES
                        ($item_id, 'Stock In', $qty, $prev_stock, $new_stock,
                         'Purchase', $po_id, 'PO Received: $po_num', NOW())");
                }
            }
        }

        // STEP 2 — Mark PO as Received (DB trigger may also fire, safe to run both)
        $conn->query("UPDATE purchase_orders
                      SET status = 'Received', delivery_date = CURDATE()
                      WHERE po_id = $po_id");

        $conn->commit();
        header("Location: purchase_orders.php?success=received");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $errorMessage = 'Error receiving PO: ' . $e->getMessage();
    }
}

// ─────────────────────────────────────────────
// Delete Purchase Order
// ─────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $po_id = intval($_GET['delete']);
    if ($conn->query("DELETE FROM purchase_orders WHERE po_id = $po_id")) {
        $successMessage = 'Purchase order deleted successfully!';
    }
}

// Delete PO Item
if (isset($_GET['delete_item'])) {
    $po_item_id = intval($_GET['delete_item']);
    $po_id      = intval($_GET['po_id']);
    $conn->query("DELETE FROM purchase_order_items WHERE po_item_id = $po_item_id");
    $conn->query("UPDATE purchase_orders SET total_amount = (
        SELECT COALESCE(SUM(quantity * unit_cost), 0)
        FROM purchase_order_items WHERE po_id = $po_id
    ) WHERE po_id = $po_id");
    header("Location: purchase_orders.php?view=$po_id");
    exit;
}

// ─────────────────────────────────────────────
// Filters & Data Fetching
// ─────────────────────────────────────────────
$filter_status   = $_GET['status']   ?? '';
$filter_supplier = $_GET['supplier'] ?? '';

$whereConditions = [];
if (!empty($filter_status))   $whereConditions[] = "po.status = '" . $conn->real_escape_string($filter_status) . "'";
if (!empty($filter_supplier)) $whereConditions[] = "po.supplier_id = " . intval($filter_supplier);
$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$purchaseOrders = $conn->query("
    SELECT po.*, s.supplier_name, s.contact_person,
           (SELECT COUNT(*) FROM purchase_order_items WHERE po_id = po.po_id) AS item_count
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
    $whereClause
    ORDER BY po.order_date DESC, po.po_id DESC
");

$suppliers      = $conn->query("SELECT * FROM suppliers WHERE status = 'Active' ORDER BY supplier_name");
$inventoryItems = $conn->query("
    SELECT i.item_id, i.item_name, i.unit, i.cost_per_unit, c.category_name
    FROM inventory_items i
    JOIN inventory_categories c ON i.category_id = c.category_id
    ORDER BY c.category_name, i.item_name
");

// View single PO
$viewPoId = isset($_GET['view']) ? intval($_GET['view']) : 0;
$viewPO   = null;
$poItems  = null;
if ($viewPoId > 0) {
    $viewPO = $conn->query("
        SELECT po.*, s.supplier_name, s.contact_person, s.contact_number, s.email, s.address
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
        WHERE po.po_id = $viewPoId
    ")->fetch_assoc();
    if ($viewPO) {
        $poItems = $conn->query("
            SELECT poi.*, i.item_name, i.unit
            FROM purchase_order_items poi
            JOIN inventory_items i ON poi.item_id = i.item_id
            WHERE poi.po_id = $viewPoId
        ");
    }
}

// Summary stats
$stats = $conn->query("
    SELECT
        COUNT(*) AS total_po,
        SUM(CASE WHEN status = 'Draft'    THEN 1 ELSE 0 END) AS draft,
        SUM(CASE WHEN status = 'Ordered'  THEN 1 ELSE 0 END) AS ordered,
        SUM(CASE WHEN status = 'Received' THEN 1 ELSE 0 END) AS received,
        SUM(total_amount) AS total_value
    FROM purchase_orders
")->fetch_assoc();

// Build inventory items array for JS (auto-fill unit cost)
$invItemsForJS = [];
$inventoryItems->data_seek(0);
while ($r = $inventoryItems->fetch_assoc()) {
    $invItemsForJS[] = [
        'id'       => $r['item_id'],
        'name'     => $r['item_name'] . ' (' . $r['category_name'] . ')',
        'unit'     => $r['unit'],
        'cost'     => $r['cost_per_unit'],
    ];
}
$inventoryItems->data_seek(0);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>MangTV - Purchase Orders</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --dark-blue:#00537A; --yellow:#FFD35B; --light-blue:#A8E8F9;
      --bg-light:#F8FBFF; --text-dark:#2c3e50;
      --sidebar-width:280px; --sidebar-collapsed:80px;
    }
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Poppins',sans-serif;background:var(--bg-light);color:var(--text-dark);overflow-x:hidden;}
    /* ── Sidebar ── */
    .sidebar{position:fixed;left:0;top:0;height:100vh;width:var(--sidebar-width);background:linear-gradient(180deg,var(--dark-blue) 0%,#006b99 100%);color:#fff;z-index:1000;overflow-y:auto;transition:all 0.3s;box-shadow:4px 0 20px rgba(0,0,0,.1);}
    .sidebar.collapsed{width:var(--sidebar-collapsed);}
    .sidebar.collapsed .brand-text,.sidebar.collapsed .nav-text{opacity:0;visibility:hidden;}
    .sidebar-header{padding:1.5rem 1.25rem;background:rgba(0,0,0,.1);border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:1rem;}
    .brand-logo{width:45px;height:45px;background:var(--yellow);border-radius:12px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(255,213,91,.3);}
    .brand-logo i{font-size:1.5rem;color:var(--dark-blue);}
    .brand-text h4{margin:0;font-size:1.3rem;font-weight:700;color:var(--yellow);}
    .brand-text p{margin:0;font-size:.75rem;opacity:.8;}
    .sidebar-nav{padding:1.5rem 0;}
    .nav-section-title{padding:.5rem 1.25rem;font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.5);margin-top:1rem;}
    .sidebar.collapsed .nav-section-title{opacity:0;height:0;padding:0;margin:0;}
    .sidebar .nav-link{color:rgba(255,255,255,.85);padding:.85rem 1.25rem;display:flex;gap:1rem;align-items:center;font-weight:500;font-size:.95rem;transition:all .3s;margin:.25rem .75rem;border-radius:10px;}
    .sidebar .nav-link i{font-size:1.2rem;width:24px;text-align:center;flex-shrink:0;}
    .sidebar .nav-link:hover{background:rgba(255,255,255,.1);color:var(--yellow);transform:translateX(5px);}
    .sidebar .nav-link.active{background:var(--yellow);color:var(--dark-blue);font-weight:600;box-shadow:0 4px 12px rgba(255,213,91,.3);}
    /* ── Main ── */
    main{margin-left:var(--sidebar-width);transition:margin-left .3s;min-height:100vh;background:var(--bg-light);}
    main.expanded{margin-left:var(--sidebar-collapsed);}
    .topbar{position:sticky;top:0;z-index:900;background:#fff;padding:1rem 1.5rem;box-shadow:0 2px 12px rgba(0,0,0,.06);border-bottom:1px solid rgba(168,232,249,.3);}
    .topbar-title h5{margin:0;color:var(--dark-blue);font-weight:700;font-size:1.4rem;}
    .toggle-btn{background:var(--light-blue);border:none;width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--dark-blue);font-size:1.1rem;transition:all .3s;cursor:pointer;}
    .toggle-btn:hover{background:var(--yellow);transform:scale(1.05);}
    .topbar-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:var(--bg-light);color:var(--dark-blue);transition:all .3s;text-decoration:none;position:relative;border:none;cursor:pointer;}
    .topbar-icon:hover{background:var(--light-blue);transform:translateY(-2px);}
    .topbar-icon .badge{position:absolute;top:-5px;right:-5px;font-size:.65rem;min-width:18px;height:18px;display:flex;align-items:center;justify-content:center;}
    .btn-logout{background:linear-gradient(135deg,var(--dark-blue) 0%,#006b99 100%);color:#fff;border:none;padding:.5rem 1.25rem;border-radius:10px;font-weight:600;font-size:.9rem;}
    /* ── Stats ── */
    .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;}
    .stat-card{background:#fff;border-radius:16px;padding:1.25rem;box-shadow:0 2px 12px rgba(0,0,0,.06);border:1px solid rgba(168,232,249,.2);}
    .stat-icon{width:45px;height:45px;display:flex;align-items:center;justify-content:center;border-radius:12px;color:#fff;font-size:1.3rem;}
    .stat-icon.blue{background:linear-gradient(135deg,var(--dark-blue) 0%,#006b99 100%);}
    .stat-icon.yellow{background:linear-gradient(135deg,var(--yellow) 0%,#ffe082 100%);color:var(--dark-blue);}
    .stat-icon.green{background:linear-gradient(135deg,#28a745 0%,#20c997 100%);}
    .stat-icon.purple{background:linear-gradient(135deg,#6f42c1 0%,#8b5cf6 100%);}
    .stat-value{font-size:1.5rem;font-weight:700;color:var(--dark-blue);}
    .stat-label{color:#6c757d;font-size:.8rem;font-weight:500;}
    /* ── Cards ── */
    .card-custom{background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.06);border:1px solid rgba(168,232,249,.2);overflow:hidden;margin-bottom:1.5rem;}
    .card-header-custom{background:linear-gradient(135deg,var(--light-blue) 0%,rgba(168,232,249,.3) 100%);padding:1.25rem 1.5rem;border-bottom:1px solid rgba(168,232,249,.3);display:flex;justify-content:space-between;align-items:center;}
    .card-header-custom h6{margin:0;font-weight:700;color:var(--dark-blue);font-size:1.1rem;}
    .card-body-custom{padding:1.5rem;}
    .filter-section{background:#fff;border-radius:16px;padding:1.25rem;box-shadow:0 2px 12px rgba(0,0,0,.06);border:1px solid rgba(168,232,249,.2);margin-bottom:1.5rem;}
    /* ── Table ── */
    .table-custom{margin:0;}
    .table-custom thead th{background:var(--yellow);color:var(--dark-blue);font-weight:600;font-size:.85rem;padding:1rem .75rem;border:none;text-transform:uppercase;letter-spacing:.5px;}
    .table-custom tbody tr:hover{background:rgba(168,232,249,.1);}
    .table-custom tbody td{padding:.85rem .75rem;vertical-align:middle;border-bottom:1px solid rgba(168,232,249,.2);font-size:.9rem;}
    /* ── Badges ── */
    .status-badge{padding:.35rem .75rem;border-radius:8px;font-weight:600;font-size:.75rem;display:inline-flex;align-items:center;gap:.25rem;}
    .status-draft{background:#e2e3e5;color:#41464b;}
    .status-ordered{background:#cfe2ff;color:#084298;}
    .status-received{background:#d1e7dd;color:#0f5132;}
    .status-cancelled{background:#f8d7da;color:#721c24;}
    /* ── Action Buttons ── */
    .btn-action{width:32px;height:32px;padding:0;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;border:none;transition:all .3s;margin:0 2px;}
    .btn-action:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.15);}
    .btn-view{background:var(--light-blue);color:var(--dark-blue);}
    .btn-edit{background:var(--yellow);color:var(--dark-blue);}
    .btn-delete{background:#dc3545;color:#fff;}
    .btn-receive{background:#28a745;color:#fff;}
    .btn-primary-custom{background:linear-gradient(135deg,var(--dark-blue) 0%,#006b99 100%);color:#fff;border:none;padding:.65rem 1.5rem;border-radius:10px;font-weight:600;}
    .alert-custom{border-radius:12px;border:none;padding:1rem 1.25rem;margin-bottom:1.5rem;}
    /* ── PO Details ── */
    .po-detail-row{display:flex;padding:.75rem 0;border-bottom:1px solid rgba(168,232,249,.2);}
    .po-detail-label{width:150px;font-weight:600;color:var(--dark-blue);}
    .po-detail-value{flex:1;}
    /* ── Modal ── */
    .modal-content{border-radius:16px;border:none;overflow:hidden;}
    .modal-header{background:linear-gradient(135deg,var(--dark-blue) 0%,#006b99 100%);color:#fff;padding:1.1rem 1.4rem;border:none;}
    .modal-header .btn-close{filter:brightness(0) invert(1);}
    .modal-body{padding:1.5rem;max-height:65vh;overflow-y:auto;}
    .modal-footer{padding:1rem 1.5rem;border-top:1px solid rgba(168,232,249,.3);}
    .form-label{font-weight:600;color:var(--dark-blue);font-size:.9rem;margin-bottom:.5rem;}
    .form-control,.form-select{border-radius:10px;border:2px solid rgba(168,232,249,.5);padding:.65rem 1rem;font-size:.9rem;transition:all .3s;}
    .form-control:focus,.form-select:focus{border-color:var(--yellow);box-shadow:0 0 0 .25rem rgba(255,213,91,.25);}
    /* ── Receive table ── */
    .receive-table{width:100%;border-collapse:collapse;font-size:.88rem;}
    .receive-table th{background:var(--bg-light);padding:.6rem .75rem;font-weight:600;color:var(--dark-blue);border-bottom:2px solid rgba(168,232,249,.4);}
    .receive-table td{padding:.55rem .75rem;border-bottom:1px solid rgba(168,232,249,.25);vertical-align:middle;}
    .receive-table tr:last-child td{border-bottom:none;}
    .receive-table input[type="number"]{border-radius:8px;border:2px solid rgba(168,232,249,.5);padding:.35rem .6rem;width:100%;font-size:.85rem;}
    .receive-table input[type="number"]:focus{border-color:var(--yellow);outline:none;box-shadow:0 0 0 .15rem rgba(255,213,91,.25);}
    footer{background:#fff;padding:1.5rem 0;margin-top:3rem;text-align:center;color:#6c757d;border-top:1px solid rgba(168,232,249,.3);font-size:.9rem;}
    @media(max-width:992px){
      .sidebar{width:var(--sidebar-collapsed);}
      .sidebar .brand-text,.sidebar .nav-text,.sidebar .nav-section-title{opacity:0;visibility:hidden;}
      main{margin-left:var(--sidebar-collapsed);}
    }
  </style>
</head>
<body>

<!-- ═══════════════════════════════ SIDEBAR ═══════════════════════════════ -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="brand-logo"><i class="fas fa-tshirt"></i></div>
    <div class="brand-text">
      <h4>MangTV Laundry</h4>
      <p>Admin Dashboard</p>
    </div>
  </div>
  <div class="sidebar-nav">
    <div class="nav-section-title">Main Menu</div>
    <ul class="nav flex-column">
      <li><a href="dashboard.php" class="nav-link"><i class="fa fa-chart-line"></i><span class="nav-text">Dashboard</span></a></li>
      <li><a href="customer_database.php" class="nav-link"><i class="fa fa-users"></i><span class="nav-text">Customers</span></a></li>
      <li><a href="digital_record.php" class="nav-link"><i class="fa fa-database"></i><span class="nav-text">Records</span></a></li>
    </ul>
    <div class="nav-section-title">Operations</div>
    <ul class="nav flex-column">
      <li><a href="order_scheduling.php" class="nav-link"><i class="fa fa-calendar-check"></i><span class="nav-text">Schedules</span></a></li>
      <li><a href="walkin.php" class="nav-link"><i class="fa fa-person-walking"></i><span class="nav-text">Walk-in</span></a></li>
      <li><a href="payments.php" class="nav-link"><i class="fa fa-credit-card"></i><span class="nav-text">Payments</span></a></li>
      <li><a href="financials.php" class="nav-link"><i class="fa fa-chart-pie"></i><span class="nav-text">Financials</span></a></li>
    </ul>
    <div class="nav-section-title">Inventory</div>
    <ul class="nav flex-column">
      <li><a href="inventory.php" class="nav-link"><i class="fa fa-boxes"></i><span class="nav-text">Inventory</span></a></li>
      <li><a href="suppliers.php" class="nav-link"><i class="fa fa-truck"></i><span class="nav-text">Suppliers</span></a></li>
      <li><a href="purchase_orders.php" class="nav-link active"><i class="fa fa-shopping-cart"></i><span class="nav-text">Purchase Orders</span></a></li>
    </ul>
    <div class="nav-section-title">Support</div>
    <ul class="nav flex-column">
      <li><a href="complaints.php" class="nav-link"><i class="fa fa-exclamation-circle"></i><span class="nav-text">Complaints</span></a></li>
      <li><a href="employees.php" class="nav-link"><i class="fa fa-user-tie"></i><span class="nav-text">Employees</span></a></li>
      <li><a href="feedback.php" class="nav-link"><i class="fa fa-comments"></i><span class="nav-text">Feedback</span></a></li>
    </ul>
    <div class="nav-section-title">Account</div>
    <ul class="nav flex-column">
      <li><a href="logout.php" class="nav-link"><i class="fa fa-right-from-bracket"></i><span class="nav-text">Logout</span></a></li>
    </ul>
  </div>
</nav>

<!-- ═══════════════════════════════ MAIN ═══════════════════════════════ -->
<main id="mainContent">
  <div class="topbar">
    <div class="d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center gap-3">
        <button class="toggle-btn" id="toggleSidebar"><i class="fa fa-bars"></i></button>
        <div class="topbar-title">
          <h5>Purchase Orders</h5>
          <small class="text-muted">Manage supplier purchase orders</small>
        </div>
      </div>
      <div class="d-flex gap-2 align-items-center">
        <a href="inventory.php" class="topbar-icon" title="Inventory Alerts">
          <i class="fa fa-boxes"></i>
          <?php if ($invNotifCount > 0): ?>
            <span class="badge bg-danger"><?= $invNotifCount ?></span>
          <?php endif; ?>
        </a>
        <a href="logout.php" class="btn btn-logout"><i class="fa fa-right-from-bracket me-2"></i>Logout</a>
      </div>
    </div>
  </div>

  <div class="container-fluid py-4 px-4">

    <?php if ($successMessage): ?>
      <div class="alert alert-success alert-custom"><i class="fa fa-check-circle me-2"></i><?= htmlspecialchars($successMessage) ?></div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
      <div class="alert alert-danger alert-custom"><i class="fa fa-exclamation-circle me-2"></i><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <!-- ══════════════ VIEW SINGLE PO ══════════════ -->
    <?php if ($viewPoId > 0 && $viewPO): ?>
      <div class="card-custom">
        <div class="card-header-custom">
          <h6><i class="fa fa-file-invoice me-2"></i>Purchase Order: <?= htmlspecialchars($viewPO['po_number']) ?></h6>
          <div class="d-flex gap-2">
            <a href="purchase_orders.php" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i>Back</a>
            <?php if ($viewPO['status'] !== 'Received' && $viewPO['status'] !== 'Cancelled'): ?>
              <button class="btn btn-success btn-sm"
                      onclick="openReceiveModal(<?= $viewPO['po_id'] ?>, '<?= htmlspecialchars($viewPO['po_number'], ENT_QUOTES) ?>')">
                <i class="fa fa-check-circle me-1"></i>Receive Order
              </button>
            <?php endif; ?>
          </div>
        </div>
        <div class="card-body-custom">
          <div class="row">
            <div class="col-md-6">
              <div class="po-detail-row"><div class="po-detail-label">PO Number:</div><div class="po-detail-value"><strong><?= htmlspecialchars($viewPO['po_number']) ?></strong></div></div>
              <div class="po-detail-row"><div class="po-detail-label">Supplier:</div><div class="po-detail-value"><?= htmlspecialchars($viewPO['supplier_name'] ?? '-') ?></div></div>
              <div class="po-detail-row"><div class="po-detail-label">Contact Person:</div><div class="po-detail-value"><?= htmlspecialchars($viewPO['contact_person'] ?? '-') ?></div></div>
              <div class="po-detail-row"><div class="po-detail-label">Contact Number:</div><div class="po-detail-value"><?= htmlspecialchars($viewPO['contact_number'] ?? '-') ?></div></div>
            </div>
            <div class="col-md-6">
              <div class="po-detail-row"><div class="po-detail-label">Order Date:</div><div class="po-detail-value"><?= date('F d, Y', strtotime($viewPO['order_date'])) ?></div></div>
              <div class="po-detail-row"><div class="po-detail-label">Expected Delivery:</div><div class="po-detail-value"><?= $viewPO['expected_delivery_date'] ? date('F d, Y', strtotime($viewPO['expected_delivery_date'])) : '-' ?></div></div>
              <div class="po-detail-row"><div class="po-detail-label">Status:</div><div class="po-detail-value"><span class="status-badge status-<?= strtolower($viewPO['status']) ?>"><?= $viewPO['status'] ?></span></div></div>
              <div class="po-detail-row"><div class="po-detail-label">Total Amount:</div><div class="po-detail-value"><strong class="text-success">₱<?= number_format($viewPO['total_amount'] ?? 0, 2) ?></strong></div></div>
            </div>
          </div>
          <?php if ($viewPO['notes']): ?>
            <div class="mt-3 p-3" style="background:var(--bg-light);border-radius:10px;"><strong>Notes:</strong> <?= nl2br(htmlspecialchars($viewPO['notes'])) ?></div>
          <?php endif; ?>
          <h6 class="mt-4 mb-3"><i class="fa fa-list me-2"></i>Order Items</h6>
          <div class="table-responsive">
            <table class="table table-bordered">
              <thead class="table-light">
                <tr><th>Item</th><th>Ordered Qty</th><th>Unit</th><th>Unit Cost</th><th>Total</th><th>Received</th></tr>
              </thead>
              <tbody>
                <?php if ($poItems && $poItems->num_rows > 0): ?>
                  <?php while ($item = $poItems->fetch_assoc()): ?>
                  <tr>
                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td><?= $item['unit'] ?></td>
                    <td>₱<?= number_format($item['unit_cost'], 2) ?></td>
                    <td>₱<?= number_format($item['quantity'] * $item['unit_cost'], 2) ?></td>
                    <td>
                      <?php if ($item['received_quantity'] > 0): ?>
                        <span class="badge bg-success"><?= $item['received_quantity'] ?> received</span>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr><td colspan="6" class="text-center text-muted">No items in this order.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    <?php else: ?>
    <!-- ══════════════ PO LIST ══════════════ -->

      <!-- Stats -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="d-flex align-items-center gap-3">
            <div class="stat-icon blue"><i class="fa fa-shopping-cart"></i></div>
            <div><div class="stat-label">Total POs</div><div class="stat-value"><?= $stats['total_po'] ?? 0 ?></div></div>
          </div>
        </div>
        <div class="stat-card">
          <div class="d-flex align-items-center gap-3">
            <div class="stat-icon yellow"><i class="fa fa-pen"></i></div>
            <div><div class="stat-label">Draft</div><div class="stat-value"><?= $stats['draft'] ?? 0 ?></div></div>
          </div>
        </div>
        <div class="stat-card">
          <div class="d-flex align-items-center gap-3">
            <div class="stat-icon blue"><i class="fa fa-truck"></i></div>
            <div><div class="stat-label">Ordered</div><div class="stat-value"><?= $stats['ordered'] ?? 0 ?></div></div>
          </div>
        </div>
        <div class="stat-card">
          <div class="d-flex align-items-center gap-3">
            <div class="stat-icon green"><i class="fa fa-check-circle"></i></div>
            <div><div class="stat-label">Received</div><div class="stat-value"><?= $stats['received'] ?? 0 ?></div></div>
          </div>
        </div>
        <div class="stat-card">
          <div class="d-flex align-items-center gap-3">
            <div class="stat-icon purple"><i class="fa fa-peso-sign"></i></div>
            <div><div class="stat-label">Total Value</div><div class="stat-value">₱<?= number_format($stats['total_value'] ?? 0, 2) ?></div></div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="filter-section">
        <form method="GET" class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Filter by Status</label>
            <select name="status" class="form-select">
              <option value="">All Status</option>
              <?php foreach (['Draft','Ordered','Received','Cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Filter by Supplier</label>
            <select name="supplier" class="form-select">
              <option value="">All Suppliers</option>
              <?php $suppliers->data_seek(0); while ($sup = $suppliers->fetch_assoc()): ?>
                <option value="<?= $sup['supplier_id'] ?>" <?= (string)$filter_supplier === (string)$sup['supplier_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($sup['supplier_name']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-4">
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary-custom"><i class="fa fa-filter me-2"></i>Apply</button>
              <a href="purchase_orders.php" class="btn btn-secondary"><i class="fa fa-times me-2"></i>Clear</a>
              <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPOModal">
                <i class="fa fa-plus me-2"></i>New PO
              </button>
            </div>
          </div>
        </form>
      </div>

      <!-- Table -->
      <div class="card-custom">
        <div class="card-header-custom">
          <h6><i class="fa fa-list me-2"></i>Purchase Orders</h6>
          <span class="badge bg-primary"><?= $purchaseOrders ? $purchaseOrders->num_rows : 0 ?> Orders</span>
        </div>
        <div class="card-body-custom p-0">
          <div class="table-responsive">
            <table class="table table-custom">
              <thead>
                <tr>
                  <th>PO Number</th><th>Supplier</th><th>Order Date</th>
                  <th>Expected Delivery</th><th>Items</th><th>Total Amount</th>
                  <th>Status</th><th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($purchaseOrders && $purchaseOrders->num_rows > 0): ?>
                  <?php while ($po = $purchaseOrders->fetch_assoc()): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($po['po_number']) ?></strong></td>
                    <td><?= htmlspecialchars($po['supplier_name'] ?? '-') ?></td>
                    <td><?= date('M d, Y', strtotime($po['order_date'])) ?></td>
                    <td><?= $po['expected_delivery_date'] ? date('M d, Y', strtotime($po['expected_delivery_date'])) : '-' ?></td>
                    <td><?= $po['item_count'] ?> item(s)</td>
                    <td><strong>₱<?= number_format($po['total_amount'] ?? 0, 2) ?></strong></td>
                    <td><span class="status-badge status-<?= strtolower($po['status']) ?>"><?= $po['status'] ?></span></td>
                    <td>
                      <a href="?view=<?= $po['po_id'] ?>" class="btn-action btn-view" title="View"><i class="fa fa-eye"></i></a>
                      <?php if ($po['status'] === 'Draft'): ?>
                        <button class="btn-action btn-edit" onclick="editPO(<?= $po['po_id'] ?>)" title="Edit"><i class="fa fa-edit"></i></button>
                      <?php endif; ?>
                      <?php if ($po['status'] !== 'Received' && $po['status'] !== 'Cancelled'): ?>
                        <button class="btn-action btn-receive"
                                onclick="openReceiveModal(<?= $po['po_id'] ?>, '<?= htmlspecialchars($po['po_number'], ENT_QUOTES) ?>')"
                                title="Receive"><i class="fa fa-check"></i></button>
                      <?php endif; ?>
                      <a href="?delete=<?= $po['po_id'] ?>" class="btn-action btn-delete"
                         onclick="return confirm('Delete this purchase order?')" title="Delete"><i class="fa fa-trash"></i></a>
                    </td>
                  </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr><td colspan="8" class="text-center py-4 text-muted">No purchase orders found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <footer><p class="mb-0">© <?= date('Y') ?> <strong>MangTV Laundry Shop</strong> — All Rights Reserved</p></footer>
  </div>
</main>

<!-- ═══════════════════════════════ ADD / EDIT PO MODAL ═══════════════════════════════ -->
<div class="modal fade" id="addPOModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" id="poForm">
        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle"><i class="fa fa-plus-circle me-2"></i>New Purchase Order</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="po_id" id="edit_po_id">
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">PO Number *</label>
              <input type="text" name="po_number" id="edit_po_number" class="form-control" required placeholder="e.g. PO-2025-001">
            </div>
            <div class="col-md-6">
              <label class="form-label">Supplier *</label>
              <select name="supplier_id" id="edit_supplier_id" class="form-select" required>
                <option value="">Select Supplier</option>
                <?php $suppliers->data_seek(0); while ($sup = $suppliers->fetch_assoc()): ?>
                  <option value="<?= $sup['supplier_id'] ?>"><?= htmlspecialchars($sup['supplier_name']) ?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Order Date *</label>
              <input type="date" name="order_date" id="edit_order_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Expected Delivery</label>
              <input type="date" name="expected_delivery" id="edit_expected_delivery" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select name="status" id="edit_status" class="form-select">
                <option value="Draft">Draft</option>
                <option value="Ordered">Ordered</option>
                <option value="Cancelled">Cancelled</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Notes</label>
              <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
            </div>
          </div>
          <h6 class="mb-3"><i class="fa fa-box me-2"></i>Order Items</h6>
          <div id="poItemsContainer"></div>
          <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="addItemRow()">
            <i class="fa fa-plus me-1"></i>Add Item
          </button>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="save_po" class="btn btn-primary-custom"><i class="fa fa-save me-2"></i>Save PO</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════ RECEIVE PO MODAL ═══════════════════════════════ -->
<div class="modal fade" id="receivePOModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" id="receiveForm">
        <input type="hidden" name="po_id"     id="receive_po_id">
        <input type="hidden" name="po_number" id="receive_po_number">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fa fa-check-circle me-2"></i>Receive Purchase Order</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <!-- Loading spinner -->
          <div id="receiveLoading" class="text-center py-4">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted">Loading order items…</p>
          </div>
          <!-- Items table (filled via JS) -->
          <div id="receiveContent" style="display:none;">
            <div class="alert alert-info mb-3" style="border-radius:10px;border:none;">
              <i class="fa fa-info-circle me-2"></i>
              Confirm the quantity received for each item. Stock will be added to inventory automatically.
            </div>
            <p class="mb-2"><strong>PO Number:</strong> <span id="receive_po_display" class="text-primary fw-bold"></span></p>
            <div class="table-responsive">
              <table class="receive-table">
                <thead>
                  <tr>
                    <th>Item</th>
                    <th>Ordered</th>
                    <th>Unit</th>
                    <th>Received Qty <span class="text-danger">*</span></th>
                  </tr>
                </thead>
                <tbody id="receiveItemsBody"></tbody>
              </table>
            </div>
          </div>
          <!-- Error state -->
          <div id="receiveError" class="alert alert-danger" style="display:none;border-radius:10px;border:none;">
            <i class="fa fa-exclamation-circle me-2"></i><span id="receiveErrorMsg"></span>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="receive_po" id="confirmReceiveBtn" class="btn btn-success" disabled>
            <i class="fa fa-boxes me-2"></i>Confirm Receive &amp; Update Inventory
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Inventory items data from PHP ──
const INV_ITEMS = <?= json_encode($invItemsForJS) ?>;

// ── Sidebar toggle ──
document.getElementById('toggleSidebar').addEventListener('click', () => {
  document.getElementById('sidebar').classList.toggle('collapsed');
  document.getElementById('mainContent').classList.toggle('expanded');
});

// ──────────────────────────────────────────────────────────
// ADD / EDIT PO MODAL helpers
// ──────────────────────────────────────────────────────────
let itemRowCount = 0;

function buildItemSelectHTML(nameAttr, selectedId = 0, selectedCost = 0) {
  let opts = '<option value="">Select Item</option>';
  INV_ITEMS.forEach(it => {
    const sel = it.id == selectedId ? 'selected' : '';
    opts += `<option value="${it.id}" data-cost="${it.cost}" ${sel}>${it.name}</option>`;
  });
  return `<select name="${nameAttr}" class="form-select form-select-sm item-select" onchange="updateUnitCost(this)" required>${opts}</select>`;
}

function buildItemRow(index, itemId = 0, qty = '', cost = '') {
  return `
    <div class="po-item-row row g-2 mb-2">
      <div class="col-md-6">${buildItemSelectHTML('items[' + index + '][item_id]', itemId)}</div>
      <div class="col-md-2">
        <input type="number" name="items[${index}][quantity]" class="form-control form-control-sm"
               placeholder="Qty" step="0.01" min="0.01" value="${qty}" required>
      </div>
      <div class="col-md-2">
        <input type="text" name="items[${index}][unit_cost]" class="form-control form-control-sm unit-cost"
               placeholder="Unit Cost" readonly style="background:#e9ecef;" value="${cost ? parseFloat(cost).toFixed(2) : ''}">
      </div>
      <div class="col-md-2">
        <button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(this)"><i class="fa fa-trash"></i></button>
      </div>
    </div>`;
}

function addItemRow() {
  const container = document.getElementById('poItemsContainer');
  container.insertAdjacentHTML('beforeend', buildItemRow(itemRowCount));
  itemRowCount++;
}

function removeItemRow(btn) {
  const rows = document.querySelectorAll('.po-item-row');
  if (rows.length > 1) btn.closest('.po-item-row').remove();
}

function updateUnitCost(selectEl) {
  const opt  = selectEl.options[selectEl.selectedIndex];
  const cost = opt ? (opt.getAttribute('data-cost') || 0) : 0;
  const row  = selectEl.closest('.po-item-row');
  const inp  = row.querySelector('.unit-cost');
  if (inp) inp.value = parseFloat(cost).toFixed(2);
}

// Reset modal to "New PO" state
document.getElementById('addPOModal').addEventListener('show.bs.modal', function (e) {
  // Only reset if NOT triggered by editPO()
  if (!e.relatedTarget && !document.getElementById('edit_po_id').value) {
    resetPOModal();
  }
});

function resetPOModal() {
  document.getElementById('poForm').reset();
  document.getElementById('edit_po_id').value = '';
  document.getElementById('edit_order_date').value = new Date().toISOString().split('T')[0];
  document.getElementById('modalTitle').innerHTML = '<i class="fa fa-plus-circle me-2"></i>New Purchase Order';
  const container = document.getElementById('poItemsContainer');
  container.innerHTML = '';
  itemRowCount = 0;
  addItemRow(); // start with one empty row
}

// New PO button
document.querySelector('[data-bs-target="#addPOModal"]')?.addEventListener('click', () => {
  resetPOModal();
});

function editPO(poId) {
  fetch('get_purchase_order.php?id=' + poId)
    .then(r => r.json())
    .then(data => {
      if (!data.success) { alert('Could not load PO data.'); return; }
      const po = data.po;
      document.getElementById('edit_po_id').value              = po.po_id;
      document.getElementById('edit_po_number').value          = po.po_number;
      document.getElementById('edit_supplier_id').value        = po.supplier_id;
      document.getElementById('edit_order_date').value         = po.order_date;
      document.getElementById('edit_expected_delivery').value  = po.expected_delivery_date || '';
      document.getElementById('edit_status').value             = po.status;
      document.getElementById('edit_notes').value              = po.notes || '';
      document.getElementById('modalTitle').innerHTML          = '<i class="fa fa-edit me-2"></i>Edit Purchase Order';

      const container = document.getElementById('poItemsContainer');
      container.innerHTML = '';
      itemRowCount = 0;
      if (data.items && data.items.length > 0) {
        data.items.forEach(item => {
          container.insertAdjacentHTML('beforeend',
            buildItemRow(itemRowCount, item.item_id, item.quantity, item.unit_cost));
          itemRowCount++;
        });
      } else {
        addItemRow();
      }
      new bootstrap.Modal(document.getElementById('addPOModal')).show();
    })
    .catch(() => alert('Network error loading PO.'));
}

// ──────────────────────────────────────────────────────────
// RECEIVE PO MODAL
// ──────────────────────────────────────────────────────────
function openReceiveModal(poId, poNumber) {
  // Reset states
  document.getElementById('receive_po_id').value      = poId;
  document.getElementById('receive_po_number').value  = poNumber;
  document.getElementById('receive_po_display').textContent = poNumber;
  document.getElementById('receiveLoading').style.display  = 'block';
  document.getElementById('receiveContent').style.display  = 'none';
  document.getElementById('receiveError').style.display    = 'none';
  document.getElementById('confirmReceiveBtn').disabled     = true;

  new bootstrap.Modal(document.getElementById('receivePOModal')).show();

  // Fetch PO items via AJAX
  fetch('get_purchase_order.php?id=' + poId)
    .then(r => r.json())
    .then(data => {
      document.getElementById('receiveLoading').style.display = 'none';

      if (!data.success) {
        document.getElementById('receiveErrorMsg').textContent = data.message || 'Failed to load PO items.';
        document.getElementById('receiveError').style.display = 'block';
        return;
      }

      const tbody = document.getElementById('receiveItemsBody');
      tbody.innerHTML = '';

      if (!data.items || data.items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No items in this order.</td></tr>';
      } else {
        data.items.forEach(item => {
          tbody.insertAdjacentHTML('beforeend', `
            <tr>
              <td><strong>${escHtml(item.item_name)}</strong></td>
              <td>${item.quantity}</td>
              <td>${escHtml(item.unit || '')}</td>
              <td>
                <input type="number"
                       name="received_qty[${item.po_item_id}]"
                       value="${item.quantity}"
                       min="0"
                       step="0.01"
                       placeholder="0"
                       required>
              </td>
            </tr>
          `);
        });
      }

      document.getElementById('receiveContent').style.display = 'block';
      document.getElementById('confirmReceiveBtn').disabled   = false;
    })
    .catch(() => {
      document.getElementById('receiveLoading').style.display = 'none';
      document.getElementById('receiveErrorMsg').textContent  = 'Network error. Please try again.';
      document.getElementById('receiveError').style.display   = 'block';
    });
}

// Small HTML-escape helper for JS-generated content
function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>
</body>
</html>
<?php $conn->close(); ?>