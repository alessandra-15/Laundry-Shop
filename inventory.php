<?php
// inventory.php — MangTV Inventory Management System
session_start();
include 'db_connect.php';

// Include notification helpers if file exists
if (file_exists('notification_helpers.php')) {
    include 'notification_helpers.php';
    $unreadNotifCount = getUnreadCount($conn);
} else {
    $unreadNotifCount = 0;
}

// Get unread inventory notifications
$invNotifCount = $conn->query("SELECT COUNT(*) as cnt FROM inventory_notifications WHERE is_read = 0")->fetch_assoc()['cnt'] ?? 0;

// Handle form submissions
$successMessage = '';
$errorMessage = '';

// Add/Edit Item
if (isset($_POST['save_item'])) {
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    $category_id = intval($_POST['category_id']);
    $item_name = $conn->real_escape_string($_POST['item_name']);
    $item_code = $conn->real_escape_string($_POST['item_code']);
    $unit = $conn->real_escape_string($_POST['unit']);
    $current_stock = floatval($_POST['current_stock']);
    $min_stock_level = floatval($_POST['min_stock_level']);
    $max_stock_level = floatval($_POST['max_stock_level']);
    $cost_per_unit = floatval($_POST['cost_per_unit']);
    $supplier_id = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 'NULL';
    $location = $conn->real_escape_string($_POST['location'] ?? '');
    
    // Check for duplicate item name
    $checkDuplicate = $conn->query("SELECT item_id FROM inventory_items WHERE item_name = '$item_name' AND item_id != $item_id");
    if ($checkDuplicate->num_rows > 0) {
        $errorMessage = "Item '$item_name' already exists! Please edit the existing item instead.";
    } else {
        if ($item_id > 0) {
            $sql = "UPDATE inventory_items SET 
                    category_id = $category_id,
                    item_name = '$item_name',
                    item_code = '$item_code',
                    unit = '$unit',
                    min_stock_level = $min_stock_level,
                    max_stock_level = $max_stock_level,
                    cost_per_unit = $cost_per_unit,
                    supplier_id = $supplier_id,
                    location = '$location'
                    WHERE item_id = $item_id";
        } else {
            $sql = "INSERT INTO inventory_items 
                    (category_id, item_name, item_code, unit, current_stock, min_stock_level, max_stock_level, cost_per_unit, supplier_id, location) 
                    VALUES 
                    ($category_id, '$item_name', '$item_code', '$unit', $current_stock, $min_stock_level, $max_stock_level, $cost_per_unit, $supplier_id, '$location')";
        }
        
        if ($conn->query($sql)) {
            $successMessage = $item_id > 0 ? 'Item updated successfully!' : 'Item added successfully!';
        } else {
            $errorMessage = 'Error: ' . $conn->error;
        }
    }
}

// Stock In/Out
if (isset($_POST['stock_transaction'])) {
    $item_id = intval($_POST['item_id']);
    $transaction_type = $_POST['transaction_type'];
    $quantity = floatval($_POST['quantity']);
    $unit_cost = floatval($_POST['unit_cost'] ?? 0);
    $supplier_id = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 'NULL';
    $notes = $conn->real_escape_string($_POST['notes'] ?? '');
    
    $result = $conn->query("SELECT current_stock, item_name FROM inventory_items WHERE item_id = $item_id");
    $item = $result->fetch_assoc();
    $previous_stock = $item['current_stock'];
    
    if (($transaction_type == 'Stock Out' || $transaction_type == 'Waste') && $quantity > $previous_stock) {
        $errorMessage = 'Error: Insufficient stock!';
    } else {
        $new_stock = ($transaction_type == 'Stock In') ? $previous_stock + $quantity : $previous_stock - $quantity;
        
        $conn->begin_transaction();
        try {
            $conn->query("UPDATE inventory_items SET current_stock = $new_stock, last_restock_date = " . ($transaction_type == 'Stock In' ? "CURDATE()" : "last_restock_date") . " WHERE item_id = $item_id");
            $conn->query("INSERT INTO inventory_transactions (item_id, transaction_type, quantity, previous_stock, new_stock, reference_type, supplier_id, unit_cost, notes, transaction_date) 
                          VALUES ($item_id, '$transaction_type', $quantity, $previous_stock, $new_stock, 'Manual', $supplier_id, $unit_cost, '$notes', NOW())");
            $conn->commit();
            $successMessage = 'Stock updated successfully!';
        } catch (Exception $e) {
            $conn->rollback();
            $errorMessage = 'Error: ' . $e->getMessage();
        }
    }
}

// Delete Item
if (isset($_GET['delete_item'])) {
    $item_id = intval($_GET['delete_item']);
    if ($conn->query("DELETE FROM inventory_items WHERE item_id = $item_id")) {
        $successMessage = 'Item deleted successfully!';
    }
}

// Fetch data for display
$categories = $conn->query("SELECT * FROM inventory_categories ORDER BY category_name");
$suppliers = $conn->query("SELECT * FROM suppliers WHERE status = 'Active' ORDER BY supplier_name");

$filter_category = $_GET['category'] ?? '';
$filter_status = $_GET['status'] ?? '';
// Get item_id from URL for highlighting
$highlight_item = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;

$whereConditions = [];
if (!empty($filter_category)) $whereConditions[] = "i.category_id = " . intval($filter_category);
if (!empty($filter_status)) $whereConditions[] = "i.status = '" . $conn->real_escape_string($filter_status) . "'";
$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$items = $conn->query("
    SELECT i.*, c.category_name, s.supplier_name
    FROM inventory_items i
    LEFT JOIN inventory_categories c ON i.category_id = c.category_id
    LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
    $whereClause
    ORDER BY i.item_name ASC
");

$stats = $conn->query("
    SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN status = 'In Stock' THEN 1 ELSE 0 END) as in_stock,
        SUM(CASE WHEN status = 'Low Stock' THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN status = 'Out of Stock' THEN 1 ELSE 0 END) as out_of_stock,
        SUM(current_stock * cost_per_unit) as total_value
    FROM inventory_items
")->fetch_assoc();

$recentTransactions = $conn->query("
    SELECT t.*, i.item_name, i.unit, s.supplier_name
    FROM inventory_transactions t
    JOIN inventory_items i ON t.item_id = i.item_id
    LEFT JOIN suppliers s ON t.supplier_id = s.supplier_id
    ORDER BY t.transaction_date DESC LIMIT 10
");

$usageStats = $conn->query("
    SELECT DATE(transaction_date) as date, SUM(CASE WHEN transaction_type IN ('Stock Out', 'Usage') THEN quantity ELSE 0 END) as used
    FROM inventory_transactions WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(transaction_date) ORDER BY date ASC
");

$labels = []; $usageData = [];
while ($row = $usageStats->fetch_assoc()) { 
    $labels[] = date('D', strtotime($row['date'])); 
    $usageData[] = $row['used']; 
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>MangTV - Inventory Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

    .topbar-actions {
      display: flex;
      gap: 0.75rem;
      align-items: center;
    }

    .topbar-icon {
      width: 40px;
      height: 40px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--bg-light);
      color: var(--dark-blue);
      transition: all 0.3s;
      text-decoration: none;
      position: relative;
      border: none;
      cursor: pointer;
    }

    .topbar-icon:hover {
      background: var(--light-blue);
      transform: translateY(-2px);
    }

    .topbar-icon .badge {
      position: absolute;
      top: -5px;
      right: -5px;
      font-size: 0.65rem;
      min-width: 18px;
      height: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 0 0.3rem;
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

    /* ===================== STAT CARDS ===================== */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    .stat-card {
      background: white;
      border-radius: 16px;
      padding: 1.5rem;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
      border: 1px solid rgba(168,232,249,0.2);
      transition: all 0.3s;
      height: 100%;
    }

    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 24px rgba(0,0,0,0.1);
    }

    .stat-icon {
      width: 60px;
      height: 60px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 14px;
      color: #fff;
      font-size: 1.5rem;
      flex-shrink: 0;
      position: relative;
      overflow: hidden;
    }

    .stat-icon::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -50%;
      width: 100px;
      height: 100px;
      background: rgba(255,255,255,0.1);
      border-radius: 50%;
    }

    .stat-icon.blue   { background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%); }
    .stat-icon.green  { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
    .stat-icon.yellow { background: linear-gradient(135deg, var(--yellow) 0%, #ffe082 100%); color: var(--dark-blue); }
    .stat-icon.red    { background: linear-gradient(135deg, #dc3545 0%, #ff6b6b 100%); }
    .stat-icon.purple { background: linear-gradient(135deg, #6f42c1 0%, #8b5cf6 100%); }

    .stat-value {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--dark-blue);
      line-height: 1;
      word-break: break-word;
    }

    .stat-label {
      color: #6c757d;
      font-size: 0.85rem;
      font-weight: 500;
      margin-bottom: 0.25rem;
    }

    @media (max-width: 1400px) {
      .stat-value { font-size: 1.3rem; }
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

    /* ===================== FILTER SECTION ===================== */
    .filter-section {
      background: white;
      border-radius: 16px;
      padding: 1.25rem;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
      border: 1px solid rgba(168,232,249,0.2);
      margin-bottom: 1.5rem;
    }

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

    /* ===================== STOCK BADGES ===================== */
    .stock-badge {
      padding: 0.35rem 0.75rem;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.75rem;
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
    }

    .stock-badge.in-stock    { background: #d1e7dd; color: #0f5132; }
    .stock-badge.low-stock   { background: #fff3cd; color: #856404; }
    .stock-badge.out-of-stock { background: #f8d7da; color: #721c24; }

    .stock-progress {
      width: 100px;
      height: 6px;
      background: #e9ecef;
      border-radius: 10px;
      overflow: hidden;
    }

    .stock-progress-bar { height: 100%; border-radius: 10px; }
    .stock-progress-bar.in-stock    { background: #28a745; }
    .stock-progress-bar.low-stock   { background: #ffc107; }
    .stock-progress-bar.out-of-stock { background: #dc3545; }

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

    .btn-stock-in  { background: #28a745; color: white; }
    .btn-stock-out { background: #dc3545; color: white; }
    .btn-edit      { background: var(--light-blue); color: var(--dark-blue); }
    .btn-delete    { background: #6c757d; color: white; }

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

    /* ===================== CHART ===================== */
    .chart-container {
      position: relative;
      height: 250px;
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
    }

    .form-control:focus,
    .form-select:focus {
      border-color: var(--yellow);
      box-shadow: 0 0 0 0.25rem rgba(255,213,91,0.25);
      outline: none;
    }

    .btn-primary-custom {
      background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
      color: white;
      border: none;
      padding: 0.65rem 1.5rem;
      border-radius: 10px;
      font-weight: 600;
    }

    .btn-secondary-custom {
      background: #6c757d;
      color: white;
      border: none;
      padding: 0.65rem 1.5rem;
      border-radius: 10px;
      font-weight: 600;
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

    /* ===================== HIGHLIGHT ROW ===================== */
    .highlight-row {
      background: #fff3cd !important;
      animation: pulse 2s;
    }

    @keyframes pulse {
      0%   { background: #fff3cd; }
      50%  { background: #ffe082; }
      100% { background: #fff3cd; }
    }

    /* ===================== RESPONSIVE ===================== */
    @media (max-width: 992px) {
      .sidebar {
        width: var(--sidebar-collapsed);
      }
      .sidebar .brand-text,
      .sidebar .nav-text,
      .sidebar .nav-section-title {
        opacity: 0;
        visibility: hidden;
      }
      main {
        margin-left: var(--sidebar-collapsed);
      }
    }

    @media (max-width: 768px) {
      .topbar { padding: 1rem; }
      .stat-card { margin-bottom: 1rem; }
      .chart-container { height: 200px; }
    }
  </style>
</head>
<body>
  <nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="brand-logo"><i class="fas fa-tshirt"></i></div>
      <div class="brand-text"><h4>MangTV Laundry Shop</h4><p>Admin Dashboard</p></div>
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
        <li class="nav-item"><a href="inventory.php" class="nav-link active"><i class="fa fa-boxes"></i><span class="nav-text">Inventory</span></a></li>
        <li class="nav-item"><a href="suppliers.php" class="nav-link"><i class="fa fa-truck"></i><span class="nav-text">Suppliers</span></a></li>
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
          <div class="topbar-title"><h5>Inventory Management</h5><small>Track supplies, stock levels & usage</small></div>
        </div>
        <div class="topbar-actions">
          <a href="inventory.php" class="topbar-icon" title="Inventory Alerts"><i class="fa fa-boxes"></i><?php if ($invNotifCount > 0): ?><span class="badge bg-danger"><?= $invNotifCount ?></span><?php endif; ?></a>
          <a href="logout.php" class="btn btn-logout"><i class="fa fa-right-from-bracket me-2"></i>Logout</a>
        </div>
      </div>
    </div>

    <div class="container-fluid py-4 px-4">
      <?php if ($successMessage): ?><div class="alert alert-success alert-custom"><i class="fa fa-check-circle me-2"></i><?= $successMessage ?></div><?php endif; ?>
      <?php if ($errorMessage): ?><div class="alert alert-danger alert-custom"><i class="fa fa-exclamation-circle me-2"></i><?= $errorMessage ?></div><?php endif; ?>

      <div class="stats-grid">
        <div class="stat-card"><div class="d-flex align-items-center gap-3"><div class="stat-icon blue"><i class="fa fa-boxes"></i></div><div><div class="stat-label">Total Items</div><div class="stat-value"><?= $stats['total_items'] ?? 0 ?></div></div></div></div>
        <div class="stat-card"><div class="d-flex align-items-center gap-3"><div class="stat-icon green"><i class="fa fa-check-circle"></i></div><div><div class="stat-label">In Stock</div><div class="stat-value"><?= $stats['in_stock'] ?? 0 ?></div></div></div></div>
        <div class="stat-card"><div class="d-flex align-items-center gap-3"><div class="stat-icon yellow"><i class="fa fa-exclamation-triangle"></i></div><div><div class="stat-label">Low Stock</div><div class="stat-value"><?= $stats['low_stock'] ?? 0 ?></div></div></div></div>
        <div class="stat-card"><div class="d-flex align-items-center gap-3"><div class="stat-icon red"><i class="fa fa-times-circle"></i></div><div><div class="stat-label">Out of Stock</div><div class="stat-value"><?= $stats['out_of_stock'] ?? 0 ?></div></div></div></div>
        <div class="stat-card"><div class="d-flex align-items-center gap-3"><div class="stat-icon purple"><i class="fa fa-peso-sign"></i></div><div><div class="stat-label">Total Value</div><div class="stat-value">₱<?= number_format($stats['total_value'] ?? 0, 2) ?></div></div></div></div>
      </div>

      <div class="row g-4 mb-4">
        <div class="col-lg-8">
          <div class="card-custom"><div class="card-header-custom"><h6><i class="fa fa-chart-line me-2"></i>Weekly Usage Trend</h6></div><div class="card-body-custom"><div class="chart-container"><canvas id="usageChart"></canvas></div></div></div>
        </div>
        <div class="col-lg-4">
          <div class="card-custom"><div class="card-header-custom"><h6><i class="fa fa-history me-2"></i>Recent Transactions</h6></div>
            <div class="card-body-custom p-0" style="max-height:280px;overflow-y:auto;">
              <?php if ($recentTransactions && $recentTransactions->num_rows > 0): ?>
                <?php while ($trans = $recentTransactions->fetch_assoc()): ?>
                  <div class="p-3 border-bottom"><div class="d-flex justify-content-between"><div><strong><?= htmlspecialchars($trans['item_name']) ?></strong><div class="small text-muted"><?= date('M d, h:i A', strtotime($trans['transaction_date'])) ?></div></div><span class="<?= $trans['transaction_type'] == 'Stock In' ? 'text-success' : 'text-danger' ?> fw-bold"><i class="fa <?= $trans['transaction_type'] == 'Stock In' ? 'fa-arrow-down' : 'fa-arrow-up' ?> me-1"></i><?= $trans['quantity'] ?> <?= $trans['unit'] ?></span></div></div>
                <?php endwhile; ?>
              <?php else: ?><div class="text-center py-4 text-muted">No recent transactions</div><?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="filter-section">
        <form method="GET" class="row g-3 align-items-end">
          <div class="col-md-4"><label class="form-label">Filter by Category</label><select name="category" class="form-select"><option value="">All Categories</option><?php mysqli_data_seek($categories, 0); while ($cat = $categories->fetch_assoc()): ?><option value="<?= $cat['category_id'] ?>" <?= $filter_category == $cat['category_id'] ? 'selected' : '' ?>><?= $cat['category_name'] ?></option><?php endwhile; ?></select></div>
          <div class="col-md-4"><label class="form-label">Filter by Status</label><select name="status" class="form-select"><option value="">All Status</option><option value="In Stock" <?= $filter_status == 'In Stock' ? 'selected' : '' ?>>In Stock</option><option value="Low Stock" <?= $filter_status == 'Low Stock' ? 'selected' : '' ?>>Low Stock</option><option value="Out of Stock" <?= $filter_status == 'Out of Stock' ? 'selected' : '' ?>>Out of Stock</option></select></div>
          <div class="col-md-4"><div class="d-flex gap-2"><button type="submit" class="btn btn-primary-custom"><i class="fa fa-filter me-2"></i>Apply</button><a href="inventory.php" class="btn btn-secondary-custom"><i class="fa fa-times me-2"></i>Clear</a><button type="button" class="btn btn-primary-custom" style="background: var(--yellow); color: var(--dark-blue);" data-bs-toggle="modal" data-bs-target="#addItemModal"><i class="fa fa-plus me-2"></i>Add Item</button></div></div>
        </form>
      </div>

      <div class="card-custom">
        <div class="card-header-custom"><h6><i class="fa fa-list me-2"></i>Inventory Items</h6><span class="badge bg-primary"><?= $items->num_rows ?> Items</span></div>
        <div class="card-body-custom p-0">
          <div class="table-responsive">
            <table class="table table-custom">
              <thead><tr><th>Item</th><th>Category</th><th>Stock Level</th><th>Status</th><th>Unit Cost</th><th>Total Value</th><th>Supplier</th><th>Actions</th></tr></thead>
              <tbody>
                <?php if ($items && $items->num_rows > 0): ?>
                  <?php while ($item = $items->fetch_assoc()): ?>
  <tr id="item-row-<?= $item['item_id'] ?>" class="<?= ($highlight_item == $item['item_id']) ? 'highlight-row' : '' ?>">
                    <td><strong><?= htmlspecialchars($item['item_name']) ?></strong><div class="small text-muted"><?= htmlspecialchars($item['item_code']) ?></div></td>
                    <td><?= htmlspecialchars($item['category_name']) ?></td>
                    <td style="min-width:150px;"><div class="d-flex align-items-center gap-2"><span class="fw-bold"><?= $item['current_stock'] ?> <?= $item['unit'] ?></span><div class="stock-progress"><div class="stock-progress-bar <?= $progressClass ?>" style="width:<?= min(100, $stockPercent) ?>%"></div></div></div><small class="text-muted">Min: <?= $item['min_stock_level'] ?> / Max: <?= $item['max_stock_level'] ?></small></td>
                    <td><span class="stock-badge <?= strtolower(str_replace(' ', '-', $item['status'])) ?>"><?php if ($item['status'] == 'In Stock'): ?><i class="fa fa-check-circle"></i><?php elseif ($item['status'] == 'Low Stock'): ?><i class="fa fa-exclamation-triangle"></i><?php else: ?><i class="fa fa-times-circle"></i><?php endif; ?> <?= $item['status'] ?></span></td>
                    <td>₱<?= number_format($item['cost_per_unit'], 2) ?></td>
                    <td><strong>₱<?= number_format($item['current_stock'] * $item['cost_per_unit'], 2) ?></strong></td>
                    <td><?= htmlspecialchars($item['supplier_name'] ?? '-') ?></td>
                    <td><div class="d-flex">
                      <button class="btn-action btn-stock-in" onclick="openStockModal(<?= $item['item_id'] ?>, '<?= addslashes($item['item_name']) ?>', '<?= $item['unit'] ?>', 'Stock In')" title="Stock In"><i class="fa fa-plus"></i></button>
                      <button class="btn-action btn-stock-out" onclick="openStockModal(<?= $item['item_id'] ?>, '<?= addslashes($item['item_name']) ?>', '<?= $item['unit'] ?>', 'Stock Out')" title="Stock Out"><i class="fa fa-minus"></i></button>
                      <button class="btn-action btn-edit" onclick="editItem(<?= $item['item_id'] ?>)" title="Edit"><i class="fa fa-edit"></i></button>
                      <a href="?delete_item=<?= $item['item_id'] ?>" class="btn-action btn-delete" onclick="return confirm('Delete this item?')" title="Delete"><i class="fa fa-trash"></i></a>
                    </div></td>
                  </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr><td colspan="8" class="text-center py-5 text-muted">No inventory items found</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <footer><p class="mb-0">© <?= date('Y') ?> <strong>MangTV Laundry Shop</strong> - All Rights Reserved</p></footer>
    </div>
  </main>

  <!-- Add/Edit Item Modal -->
  <div class="modal fade" id="addItemModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST" id="itemForm">
      <div class="modal-header"><h5 class="modal-title" id="modalTitle"><i class="fa fa-plus-circle me-2"></i>Add New Item</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="item_id" id="edit_item_id">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Item Name *</label><input type="text" name="item_name" id="edit_item_name" class="form-control" required></div>
          <div class="col-md-6"><label class="form-label">Item Code</label><input type="text" name="item_code" id="edit_item_code" class="form-control"></div>
          <div class="col-md-6"><label class="form-label">Category *</label><select name="category_id" id="edit_category_id" class="form-select" required><option value="">Select Category</option><?php mysqli_data_seek($categories, 0); while ($cat = $categories->fetch_assoc()): ?><option value="<?= $cat['category_id'] ?>"><?= $cat['category_name'] ?></option><?php endwhile; ?></select></div>
          <div class="col-md-6"><label class="form-label">Unit *</label><select name="unit" id="edit_unit" class="form-select" required><option value="liters">Liters</option><option value="kilograms">Kilograms</option><option value="grams">Grams</option><option value="pieces">Pieces</option><option value="milliliters">Milliliters</option></select></div>
          <div class="col-md-4"><label class="form-label">Current Stock *</label><input type="number" name="current_stock" id="edit_current_stock" class="form-control" step="0.01" min="0" required></div>
          <div class="col-md-4"><label class="form-label">Min Stock Level *</label><input type="number" name="min_stock_level" id="edit_min_stock" class="form-control" step="0.01" min="0" value="5" required></div>
          <div class="col-md-4"><label class="form-label">Max Stock Level</label><input type="number" name="max_stock_level" id="edit_max_stock" class="form-control" step="0.01" min="0" value="100"></div>
          <div class="col-md-4"><label class="form-label">Cost per Unit *</label><input type="number" name="cost_per_unit" id="edit_cost" class="form-control" step="0.01" min="0" required></div>
          <div class="col-md-4"><label class="form-label">Supplier</label><select name="supplier_id" id="edit_supplier_id" class="form-select"><option value="">None</option><?php mysqli_data_seek($suppliers, 0); while ($sup = $suppliers->fetch_assoc()): ?><option value="<?= $sup['supplier_id'] ?>"><?= $sup['supplier_name'] ?></option><?php endwhile; ?></select></div>
          <div class="col-md-4"><label class="form-label">Location</label><input type="text" name="location" id="edit_location" class="form-control" placeholder="e.g., Shelf A1"></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button><button type="submit" name="save_item" class="btn btn-primary-custom"><i class="fa fa-save me-2"></i>Save Item</button></div>
    </form>
  </div></div></div>

  <!-- Stock Transaction Modal -->
  <div class="modal fade" id="stockModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST">
      <div class="modal-header"><h5 class="modal-title" id="stockModalTitle"><i class="fa fa-box me-2"></i>Stock Transaction</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="item_id" id="stock_item_id"><input type="hidden" name="transaction_type" id="stock_transaction_type">
        <div class="mb-3"><label class="form-label">Item</label><input type="text" id="stock_item_display" class="form-control" readonly></div>
        <div class="mb-3"><label class="form-label">Quantity *</label><input type="number" name="quantity" id="stock_quantity" class="form-control" step="0.01" min="0.01" required></div>
        <div class="mb-3" id="costField"><label class="form-label">Unit Cost (₱)</label><input type="number" name="unit_cost" id="stock_unit_cost" class="form-control" step="0.01" min="0" value="0"></div>
        <div class="mb-3" id="supplierField"><label class="form-label">Supplier (Optional)</label><select name="supplier_id" class="form-select"><option value="">None</option><?php mysqli_data_seek($suppliers, 0); while ($sup = $suppliers->fetch_assoc()): ?><option value="<?= $sup['supplier_id'] ?>"><?= $sup['supplier_name'] ?></option><?php endwhile; ?></select></div>
        <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button><button type="submit" name="stock_transaction" class="btn btn-primary-custom"><i class="fa fa-check me-2"></i>Confirm</button></div>
    </form>
  </div></div></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.getElementById('toggleSidebar').addEventListener('click', () => { document.getElementById('sidebar').classList.toggle('collapsed'); document.getElementById('mainContent').classList.toggle('expanded'); });
    
    new Chart(document.getElementById('usageChart').getContext('2d'), {
      type: 'line', data: { labels: <?= json_encode($labels) ?>, datasets: [{ label: 'Items Used', data: <?= json_encode($usageData) ?>, borderColor: '#00537A', backgroundColor: 'rgba(0,83,122,0.1)', tension: 0.4, fill: true, borderWidth: 3 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });

    function openStockModal(itemId, itemName, unit, type) {
      document.getElementById('stock_item_id').value = itemId;
      document.getElementById('stock_item_display').value = itemName + ' (' + unit + ')';
      document.getElementById('stock_transaction_type').value = type;
      document.getElementById('stockModalTitle').innerHTML = '<i class="fa fa-box me-2"></i>' + type;
      document.getElementById('costField').style.display = type === 'Stock In' ? 'block' : 'none';
      document.getElementById('supplierField').style.display = type === 'Stock In' ? 'block' : 'none';
      new bootstrap.Modal(document.getElementById('stockModal')).show();
    }

    function editItem(itemId) {
      fetch('get_inventory_item.php?id=' + itemId).then(r => r.json()).then(data => {
        if (data.success) {
          const i = data.item;
          document.getElementById('edit_item_id').value = i.item_id;
          document.getElementById('edit_item_name').value = i.item_name;
          document.getElementById('edit_item_code').value = i.item_code || '';
          document.getElementById('edit_category_id').value = i.category_id;
          document.getElementById('edit_unit').value = i.unit;
          document.getElementById('edit_current_stock').value = i.current_stock;
          document.getElementById('edit_min_stock').value = i.min_stock_level;
          document.getElementById('edit_max_stock').value = i.max_stock_level || '';
          document.getElementById('edit_cost').value = i.cost_per_unit;
          document.getElementById('edit_supplier_id').value = i.supplier_id || '';
          document.getElementById('edit_location').value = i.location || '';
          document.getElementById('modalTitle').innerHTML = '<i class="fa fa-edit me-2"></i>Edit Item';
          new bootstrap.Modal(document.getElementById('addItemModal')).show();
        }
        
      });
    }
  </script>
</body>
</html>
<?php $conn->close(); ?>