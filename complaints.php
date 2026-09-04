<?php
// complaints.php — MangTV Complaints Management (with Update Status feature)
include 'db_connect.php';

// Include notification helpers if file exists
if (file_exists('notification_helpers.php')) {
    include 'notification_helpers.php';
    $unreadNotifCount = getUnreadCount($conn);
} else {
    $unreadNotifCount = 0;
}

// Summary counts
$totQ = $conn->query("SELECT COUNT(*) AS c FROM complaints");
$tot = $totQ ? ($totQ->fetch_assoc()['c'] ?? 0) : 0;

$resQ = $conn->query("SELECT COUNT(*) AS c FROM complaints WHERE status = 'Resolved'");
$resolved = $resQ ? ($resQ->fetch_assoc()['c'] ?? 0) : 0;

$inprogQ = $conn->query("SELECT COUNT(*) AS c FROM complaints WHERE status = 'In Progress'");
$inprogress = $inprogQ ? ($inprogQ->fetch_assoc()['c'] ?? 0) : 0;

$pendingQ = $conn->query("SELECT COUNT(*) AS c FROM complaints WHERE status NOT IN ('Resolved','In Progress')");
$pending = $pendingQ ? ($pendingQ->fetch_assoc()['c'] ?? 0) : 0;

// Monthly trend (current year)
$months = [];
$monthCounts = [];
for ($i = 1; $i <= 12; $i++) {
    $mname = date('M', mktime(0, 0, 0, $i, 1));
    $q = $conn->query("SELECT COUNT(*) AS c FROM complaints WHERE YEAR(date_reported)=YEAR(CURDATE()) AND MONTH(date_reported)=$i");
    $c = $q ? (int)$q->fetch_assoc()['c'] : 0;
    $months[] = $mname;
    $monthCounts[] = $c;
}

// Status distribution
$statusQ = $conn->query("SELECT status, COUNT(*) AS c FROM complaints GROUP BY status");
$statusLabels = [];
$statusCounts = [];
while ($s = $statusQ->fetch_assoc()) {
    $statusLabels[] = $s['status'];
    $statusCounts[] = (int)$s['c'];
}

// Lost / missing incidents
$lostQ = $conn->query("SELECT complaint_id, customer_id, issue_description, status, date_reported FROM complaints WHERE issue_description LIKE '%lost%' OR issue_description LIKE '%missing%' ORDER BY date_reported DESC LIMIT 6");

// Recent complaints (table) — include remarks
$recentQ = $conn->query("SELECT complaint_id, customer_id, issue_description, status, date_reported, date_resolved, handled_by, remarks FROM complaints ORDER BY date_reported DESC LIMIT 25");

// Resolution time average
$avgResolutionQ = $conn->query("
    SELECT AVG(DATEDIFF(date_resolved, date_reported)) AS avg_days
    FROM complaints
    WHERE date_resolved IS NOT NULL
");
$avgResolution = $avgResolutionQ ? round($avgResolutionQ->fetch_assoc()['avg_days'] ?? 0, 1) : 0;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>MangTV Admin - Complaints</title>

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

    /* ── Sidebar ── */
    .sidebar {
      position: fixed; left: 0; top: 0; height: 100vh;
      width: var(--sidebar-width);
      background: linear-gradient(180deg, var(--dark-blue) 0%, #006b99 100%);
      color: #fff; padding-top: 0; z-index: 1000;
      overflow-y: auto; overflow-x: hidden;
      transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
      box-shadow: 4px 0 20px rgba(0,0,0,0.1);
    }
    .sidebar::-webkit-scrollbar { width: 6px; }
    .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.1); }
    .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 3px; }
    .sidebar.collapsed { width: var(--sidebar-collapsed); }
    .sidebar.collapsed .brand-text,
    .sidebar.collapsed .nav-text { opacity: 0; visibility: hidden; }

    .sidebar-header {
      padding: 1.5rem 1.25rem;
      background: rgba(0,0,0,0.1);
      border-bottom: 1px solid rgba(255,255,255,0.1);
      display: flex; align-items: center; gap: 1rem;
    }
    .brand-logo {
      width: 45px; height: 45px;
      background: var(--yellow); border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0; box-shadow: 0 4px 12px rgba(255,213,91,0.3);
    }
    .brand-logo i { font-size: 1.5rem; color: var(--dark-blue); }
    .brand-text { transition: all 0.3s; }
    .brand-text h4 { margin: 0; font-size: 1.3rem; font-weight: 700; color: var(--yellow); }
    .brand-text p  { margin: 0; font-size: 0.75rem; opacity: 0.8; }

    .sidebar-nav { padding: 1.5rem 0; }
    .nav-section-title {
      padding: 0.5rem 1.25rem;
      font-size: 0.7rem; font-weight: 600;
      text-transform: uppercase; letter-spacing: 1px;
      color: rgba(255,255,255,0.5); margin-top: 1rem;
      transition: all 0.3s;
    }
    .sidebar.collapsed .nav-section-title { opacity: 0; height: 0; padding: 0; margin: 0; }

    .sidebar .nav-link {
      color: rgba(255,255,255,0.85);
      padding: 0.85rem 1.25rem;
      display: flex; gap: 1rem; align-items: center;
      font-weight: 500; font-size: 0.95rem;
      transition: all 0.3s;
      margin: 0.25rem 0.75rem; border-radius: 10px; position: relative;
    }
    .sidebar .nav-link i { font-size: 1.2rem; width: 24px; text-align: center; flex-shrink: 0; }
    .sidebar .nav-link .nav-text { transition: all 0.3s; white-space: nowrap; }
    .sidebar .nav-link:hover { background: rgba(255,255,255,0.1); color: var(--yellow); transform: translateX(5px); }
    .sidebar .nav-link.active {
      background: var(--yellow); color: var(--dark-blue);
      font-weight: 600; box-shadow: 0 4px 12px rgba(255,213,91,0.3);
    }
    .sidebar .nav-link.active::before {
      content: ''; position: absolute; left: 0; top: 50%;
      transform: translateY(-50%); width: 4px; height: 70%;
      background: var(--dark-blue); border-radius: 0 4px 4px 0;
    }

    /* ── Main ── */
    main {
      margin-left: var(--sidebar-width);
      transition: margin-left 0.3s cubic-bezier(0.4,0,0.2,1);
      min-height: 100vh; background: var(--bg-light);
    }
    main.expanded { margin-left: var(--sidebar-collapsed); }

    /* ── Topbar ── */
    .topbar {
      position: sticky; top: 0; z-index: 900;
      background: white; padding: 1rem 1.5rem;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
      border-bottom: 1px solid rgba(168,232,249,0.3);
    }
    .topbar-title h5 { margin: 0; color: var(--dark-blue); font-weight: 700; font-size: 1.4rem; }
    .topbar-title small { color: #6c757d; font-size: 0.85rem; }

    .toggle-btn {
      background: var(--light-blue); border: none;
      width: 40px; height: 40px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      color: var(--dark-blue); font-size: 1.1rem;
      transition: all 0.3s; cursor: pointer;
    }
    .toggle-btn:hover { background: var(--yellow); transform: scale(1.05); }

    .topbar-actions { display: flex; gap: 0.75rem; align-items: center; }
    .topbar-icon-wrapper { position: relative; }
    .topbar-icon {
      width: 40px; height: 40px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      background: var(--bg-light); color: var(--dark-blue);
      transition: all 0.3s; text-decoration: none; position: relative;
      border: none; cursor: pointer;
    }
    .topbar-icon:hover { background: var(--light-blue); transform: translateY(-2px); }
    .topbar-icon .badge {
      position: absolute; top: -5px; right: -5px;
      font-size: 0.65rem; min-width: 18px; height: 18px;
      display: flex; align-items: center; justify-content: center; padding: 0 0.3rem;
    }

    /* ── Notification Dropdown ── */
    .notification-dropdown {
      position: absolute; top: calc(100% + 10px); right: 0;
      width: 400px; max-height: 580px;
      background: white; border-radius: 12px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.12);
      opacity: 0; visibility: hidden; transform: translateY(-10px);
      transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
      z-index: 9999; border: 1px solid #e0e0e0;
      display: flex; flex-direction: column;
    }
    .notification-dropdown.show { opacity: 1; visibility: visible; transform: translateY(0); }
    .notification-header {
      padding: 1rem 1.25rem; background: white; color: var(--dark-blue);
      display: flex; justify-content: space-between; align-items: center;
      border-bottom: 1px solid rgba(168,232,249,0.2);
    }
    .notification-header h6 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--dark-blue); }
    .notification-tabs {
      display: flex; gap: 0.5rem; padding: 0.75rem 1.25rem;
      background: white; border-bottom: 1px solid rgba(168,232,249,0.2);
    }
    .notification-tab {
      padding: 0.4rem 1rem; border-radius: 16px;
      font-size: 0.8rem; font-weight: 600;
      background: transparent; color: #6c757d; border: none; cursor: pointer; transition: all 0.3s;
    }
    .notification-tab.active { background: var(--dark-blue); color: white; box-shadow: 0 2px 6px rgba(0,83,122,0.2); }
    .notification-list {
      max-height: 350px; overflow-y: auto;
      padding: 0.5rem 0.75rem; background: var(--bg-light);
    }
    .notification-list::-webkit-scrollbar { width: 4px; }
    .notification-list::-webkit-scrollbar-track { background: transparent; }
    .notification-list::-webkit-scrollbar-thumb { background: rgba(168,232,249,0.5); border-radius: 10px; }
    .notification-item {
      padding: 1rem; border-radius: 12px; margin-bottom: 0.5rem;
      background: white; border: 1px solid rgba(168,232,249,0.15);
      cursor: pointer; transition: all 0.3s; position: relative;
      box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .notification-item:hover { border-color: var(--light-blue); transform: translateX(2px); box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    .notification-item.unread { background: white; border-color: var(--dark-blue); border-left-width: 3px; }
    .notification-icon {
      width: 45px; height: 45px; border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.2rem; flex-shrink: 0; box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    .notification-icon.new-customer  { background: linear-gradient(135deg,#28a745,#34ce57); color:white; }
    .notification-icon.new-schedule,
    .notification-icon.new_schedule  { background: linear-gradient(135deg,var(--dark-blue),#0077b6); color:white; }
    .notification-icon.new-complaint,
    .notification-icon.new_complaint { background: linear-gradient(135deg,#dc3545,#ff6b6b); color:white; }
    .notification-icon.new-feedback,
    .notification-icon.new_feedback  { background: linear-gradient(135deg,#ffc107,#ffd93d); color:var(--dark-blue); }
    .notification-icon.payment-received,
    .notification-icon.payment_received { background: linear-gradient(135deg,#198754,#51cf66); color:white; }
    .notification-icon.schedule-updated,
    .notification-icon.schedule_updated { background: linear-gradient(135deg,#0dcaf0,#4dabf7); color:white; }
    .notification-content { flex: 1; min-width: 0; }
    .notification-title { font-size: 0.9rem; font-weight: 700; color: var(--dark-blue); margin-bottom: 0.3rem; }
    .notification-message { font-size: 0.8rem; color: #6c757d; margin-bottom: 0.5rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .notification-time { font-size: 0.7rem; color: #adb5bd; display: flex; align-items: center; gap: 0.25rem; }
    .notification-footer { padding: 0.75rem 1.25rem; background: white; text-align: center; border-top: 1px solid rgba(168,232,249,0.2); }
    .notification-empty { padding: 3rem 1rem; text-align: center; color: #6c757d; }
    .notification-empty i { font-size: 2.5rem; color: rgba(168,232,249,0.6); margin-bottom: 0.75rem; opacity: 0.5; }

    .btn-logout {
      background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
      color: white; border: none; padding: 0.5rem 1.25rem;
      border-radius: 10px; font-weight: 600; font-size: 0.9rem; transition: all 0.3s;
    }
    .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,83,122,0.3); color: white; }

    /* ── Stat Cards ── */
    .stat-card {
      background: white; border-radius: 16px; padding: 1.5rem;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
      border: 1px solid rgba(168,232,249,0.2); transition: all 0.3s; height: 100%;
    }
    .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
    .stat-card-content { display: flex; gap: 1.25rem; align-items: center; }
    .stat-icon {
      width: 60px; height: 60px; display: flex; align-items: center;
      justify-content: center; border-radius: 14px; color: #fff;
      font-size: 1.5rem; flex-shrink: 0; position: relative; overflow: hidden;
    }
    .stat-icon::before {
      content: ''; position: absolute; top: -50%; right: -50%;
      width: 100px; height: 100px; background: rgba(255,255,255,0.1); border-radius: 50%;
    }
    .stat-icon.blue   { background: linear-gradient(135deg, var(--dark-blue), #006b99); }
    .stat-icon.green  { background: linear-gradient(135deg, #28a745, #20c997); }
    .stat-icon.cyan   { background: linear-gradient(135deg, #0ea5a0, #17a2b8); }
    .stat-icon.yellow { background: linear-gradient(135deg, var(--yellow), #ffe082); color: var(--dark-blue); }
    .stat-icon.orange { background: linear-gradient(135deg, #ff6b6b, #ffa500); }
    .stat-info { flex: 1; }
    .stat-label  { color: #6c757d; font-size: 0.85rem; font-weight: 500; margin-bottom: 0.25rem; }
    .stat-value  { font-size: 1.5rem; font-weight: 700; color: var(--dark-blue); line-height: 1; word-break: break-word; }
    @media (max-width: 1400px) { .stat-value { font-size: 1.3rem; } }

    /* ── Cards ── */
    .card-custom {
      background: white; border-radius: 16px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
      border: 1px solid rgba(168,232,249,0.2); overflow: hidden;
    }
    .card-header-custom {
      background: linear-gradient(135deg, var(--light-blue) 0%, rgba(168,232,249,0.3) 100%);
      padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(168,232,249,0.3);
    }
    .card-header-custom h6 {
      margin: 0; font-weight: 700; color: var(--dark-blue);
      font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem;
    }
    .card-body-custom { padding: 1.5rem; }

    /* ── Table ── */
    .table-custom { margin: 0; }
    .table-custom thead th {
      background: var(--yellow); color: var(--dark-blue);
      font-weight: 600; font-size: 0.85rem; padding: 1rem 0.75rem;
      border: none; text-transform: uppercase; letter-spacing: 0.5px;
      text-align: center; vertical-align: middle;
    }
    .table-custom tbody tr { transition: all 0.3s; }
    .table-custom tbody tr:hover { background: rgba(168,232,249,0.1); }
    .table-custom tbody td {
      padding: 1rem 0.75rem; vertical-align: middle;
      border-bottom: 1px solid rgba(168,232,249,0.2);
      font-size: 0.9rem; text-align: center;
    }

    /* ── Badges ── */
    .badge-custom {
      padding: 0.4rem 0.75rem; border-radius: 8px;
      font-weight: 600; font-size: 0.75rem; letter-spacing: 0.5px;
    }

    /* ── Update button ── */
    .btn-update-status {
      background: linear-gradient(135deg, var(--dark-blue), #006b99);
      color: white; border: none; border-radius: 8px;
      font-size: 0.78rem; font-weight: 600;
      padding: 0.38rem 0.85rem;
      transition: all 0.25s; white-space: nowrap;
    }
    .btn-update-status:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,83,122,0.35);
      color: white;
    }

    /* ── Row flash ── */
    @keyframes rowFlash {
      0%   { background: rgba(40,167,69,0.18); }
      100% { background: transparent; }
    }
    .row-flash { animation: rowFlash 2s ease forwards; }

    /* ── Lost Items ── */
    .lost-item {
      padding: 1rem; border-radius: 12px; background: var(--bg-light);
      margin-bottom: 0.75rem; transition: all 0.3s;
      border: 1px solid rgba(168,232,249,0.3);
    }
    .lost-item:hover { background: white; box-shadow: 0 4px 12px rgba(0,0,0,0.08); transform: translateX(5px); }
    .lost-item-header { font-weight: 600; color: var(--dark-blue); margin-bottom: 0.5rem; font-size: 0.95rem; }
    .lost-item-desc   { font-size: 0.85rem; color: #6c757d; margin-bottom: 0.5rem; }

    /* ── Charts ── */
    .chart-container { position: relative; height: 320px; }

    /* ── Quick Actions ── */
    .btn-action {
      border-radius: 10px; padding: 0.6rem 1.25rem;
      font-weight: 600; transition: all 0.3s; border: 2px solid;
    }
    .btn-action:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }

    /* ── Modal polish ── */
    #updateStatusModal .modal-content {
      border-radius: 18px; border: none;
      box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    }
    #updateStatusModal .modal-header {
      background: linear-gradient(135deg, var(--dark-blue), #006b99);
      border-radius: 18px 18px 0 0; border: none; padding: 1.25rem 1.5rem;
    }
    #updateStatusModal .modal-body  { padding: 1.75rem 1.5rem; }
    #updateStatusModal .modal-footer { border: none; padding: 1rem 1.5rem 1.5rem; gap: 0.75rem; }
    #updateStatusModal .form-select,
    #updateStatusModal .form-control {
      border-radius: 10px;
      border: 1.5px solid #d0e8f0;
      font-size: 0.9rem; padding: 0.6rem 1rem;
      font-family: 'Poppins', sans-serif;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    #updateStatusModal .form-select:focus,
    #updateStatusModal .form-control:focus {
      border-color: var(--dark-blue);
      box-shadow: 0 0 0 3px rgba(0,83,122,0.12);
    }
    #updateStatusModal .form-label {
      font-size: 0.88rem; font-weight: 600; color: var(--dark-blue); margin-bottom: 0.4rem;
    }

    /* ── Footer ── */
    footer {
      background: white; padding: 1.5rem 0; margin-top: 3rem;
      text-align: center; color: #6c757d;
      border-top: 1px solid rgba(168,232,249,0.3); font-size: 0.9rem;
    }

    /* ── Responsive ── */
    @media (max-width: 992px) {
      .sidebar { width: var(--sidebar-collapsed); }
      .sidebar .brand-text, .sidebar .nav-text, .sidebar .nav-section-title { opacity: 0; visibility: hidden; }
      main { margin-left: var(--sidebar-collapsed); }
      .notification-dropdown { width: 90vw; right: -150px; }
    }
    @media (max-width: 768px) {
      .topbar { padding: 1rem; }
      .stat-card { margin-bottom: 1rem; }
      .chart-container { height: 250px; }
    }
  </style>
</head>
<body>

<!-- ════════════════════════════════════════
     SIDEBAR
════════════════════════════════════════ -->
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
        <li class="nav-item"><a href="suppliers.php" class="nav-link"><i class="fa fa-truck"></i><span class="nav-text">Suppliers</span></a></li>
        <li class="nav-item"><a href="purchase_orders.php" class="nav-link"><i class="fa fa-shopping-cart"></i><span class="nav-text">Purchase Orders</span></a></li>
      </ul>

    <div class="nav-section-title">Support</div>
    <ul class="nav flex-column">
      <li class="nav-item"><a href="complaints.php" class="nav-link active"><i class="fa fa-exclamation-circle"></i><span class="nav-text">Complaints</span></a></li>
      <li class="nav-item"><a href="employees.php" class="nav-link"><i class="fa fa-user-tie"></i><span class="nav-text">Employees</span></a></li>
      <li class="nav-item"><a href="feedback.php" class="nav-link"><i class="fa fa-comments"></i><span class="nav-text">Feedback</span></a></li>
    </ul>

    <div class="nav-section-title">Account</div>
    <ul class="nav flex-column">
      <li class="nav-item"><a href="logout.php" class="nav-link"><i class="fa fa-right-from-bracket"></i><span class="nav-text">Logout</span></a></li>
    </ul>
  </div>
</nav>

<!-- ════════════════════════════════════════
     MAIN CONTENT
════════════════════════════════════════ -->
<main id="mainContent">

  <!-- Topbar -->
  <div class="topbar">
    <div class="d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center gap-3">
        <button class="toggle-btn" id="toggleSidebar"><i class="fa fa-bars"></i></button>
        <div class="topbar-title">
          <h5>Complaints Management</h5>
          <small>Monitor and resolve customer issues efficiently</small>
        </div>
      </div>

      <div class="topbar-actions">
        <!-- Notification Bell -->
        <div class="topbar-icon-wrapper">
          <button class="topbar-icon" id="notificationBtn">
            <i class="fa fa-bell"></i>
            <span class="badge bg-danger" id="notifBadge" style="<?= $unreadNotifCount > 0 ? '' : 'display:none;' ?>">
              <?= $unreadNotifCount ?>
            </span>
          </button>

          <div class="notification-dropdown" id="notificationDropdown">
            <div class="notification-header">
              <h6><i class="fa fa-bell me-2"></i>Notifications</h6>
              <button class="btn btn-sm" id="markAllReadBtn"
                style="font-size:0.7rem;padding:0.35rem 0.85rem;border-radius:6px;background:var(--dark-blue);color:white;border:none;font-weight:600;">
                Mark all read
              </button>
            </div>
            <div class="notification-tabs">
              <button class="notification-tab active" data-filter="all">All</button>
              <button class="notification-tab" data-filter="unread">Unread</button>
            </div>
            <div class="notification-list" id="notificationList">
              <div class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                  <span class="visually-hidden">Loading…</span>
                </div>
              </div>
            </div>
            <div class="notification-footer">
              <small class="text-muted" style="font-size:0.7rem;">
                <i class="fa fa-info-circle me-1"></i>Tap notification to view details
              </small>
            </div>
          </div>
        </div>

        <a href="logout.php" class="btn btn-logout">
          <i class="fa fa-right-from-bracket me-2"></i>Logout
        </a>
      </div>
    </div>
  </div><!-- /topbar -->

  <div class="container-fluid py-4 px-4">

    <!-- ── Summary Cards ── -->
    <div class="row g-4 mb-4">
      <div class="col-xl-3 col-md-6">
        <div class="stat-card">
          <div class="stat-card-content">
            <div class="stat-icon blue"><i class="fa fa-clipboard-list"></i></div>
            <div class="stat-info">
              <div class="stat-label">Total Complaints</div>
              <div class="stat-value"><?= number_format($tot) ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-md-6">
        <div class="stat-card">
          <div class="stat-card-content">
            <div class="stat-icon green"><i class="fa fa-check-circle"></i></div>
            <div class="stat-info">
              <div class="stat-label">Resolved</div>
              <div class="stat-value"><?= number_format($resolved) ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-md-6">
        <div class="stat-card">
          <div class="stat-card-content">
            <div class="stat-icon cyan"><i class="fa fa-spinner fa-pulse"></i></div>
            <div class="stat-info">
              <div class="stat-label">In Progress</div>
              <div class="stat-value"><?= number_format($inprogress) ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-md-6">
        <div class="stat-card">
          <div class="stat-card-content">
            <div class="stat-icon yellow"><i class="fa fa-triangle-exclamation"></i></div>
            <div class="stat-info">
              <div class="stat-label">Pending</div>
              <div class="stat-value"><?= number_format($pending) ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Additional Metrics ── -->
    <div class="row g-4 mb-4">
      <div class="col-md-6">
        <div class="stat-card">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <div class="stat-label">Resolution Rate</div>
              <div class="stat-value text-success">
                <?= $tot > 0 ? number_format(($resolved / $tot) * 100, 1) : 0 ?>%
              </div>
            </div>
            <div class="stat-icon green" style="width:50px;height:50px;">
              <i class="fa fa-chart-line" style="font-size:1.3rem;"></i>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="stat-card">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <div class="stat-label">Avg Resolution Time</div>
              <div class="stat-value text-primary"><?= $avgResolution ?> days</div>
            </div>
            <div class="stat-icon blue" style="width:50px;height:50px;">
              <i class="fa fa-clock" style="font-size:1.3rem;"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Charts ── -->
    <div class="row g-4 mb-4">
      <div class="col-lg-8">
        <div class="card-custom">
          <div class="card-header-custom">
            <h6><i class="fa fa-chart-bar me-2"></i>Monthly Complaints Trend (<?= date('Y') ?>)</h6>
          </div>
          <div class="card-body-custom">
            <div class="chart-container"><canvas id="monthlyComplaintsChart"></canvas></div>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card-custom">
          <div class="card-header-custom">
            <h6><i class="fa fa-pie-chart me-2"></i>Status Distribution</h6>
          </div>
          <div class="card-body-custom">
            <div class="chart-container"><canvas id="statusPieChart"></canvas></div>
            <div class="mt-3">
              <?php
                $total_complaints = array_sum($statusCounts);
                $colors = ['#28a745','#0ea5a0','#ffc107','#ff6b6b'];
                for ($i = 0; $i < count($statusLabels); $i++):
                  $percent = $total_complaints > 0 ? ($statusCounts[$i] / $total_complaints) * 100 : 0;
                  $color   = $colors[$i] ?? '#6c757d';
              ?>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-2">
                  <div style="width:12px;height:12px;background:<?= $color ?>;border-radius:3px;"></div>
                  <span class="small"><?= htmlspecialchars($statusLabels[$i]) ?></span>
                </div>
                <span class="small fw-bold"><?= number_format($percent, 1) ?>%</span>
              </div>
              <?php endfor; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Complaints Table + Right Column ── -->
    <div class="row g-4 mb-4">

      <!-- Complaints Table -->
      <div class="col-lg-8">
        <div class="card-custom">
          <div class="card-header-custom">
            <div class="d-flex justify-content-between align-items-center">
              <h6><i class="fa fa-table me-2"></i>All Complaints</h6>
              <span class="badge bg-primary badge-custom">
                <?= $recentQ ? $recentQ->num_rows : 0 ?> Records
              </span>
            </div>
          </div>
          <div class="card-body-custom p-0">
            <div class="table-responsive">
              <table class="table table-custom">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Issue Description</th>
                    <th>Status</th>
                    <th>Reported</th>
                    <th>Resolved</th>
                    <th>Handler</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($recentQ && $recentQ->num_rows > 0): ?>
                    <?php while ($c = $recentQ->fetch_assoc()): ?>
                      <tr id="row-<?= $c['complaint_id'] ?>">

                        <td><strong class="text-primary">#<?= htmlspecialchars($c['complaint_id']) ?></strong></td>

                        <td><?= htmlspecialchars($c['customer_id']) ?></td>

                        <td class="text-start">
                          <span class="d-inline-block text-truncate" style="max-width:200px;">
                            <?= htmlspecialchars($c['issue_description']) ?>
                          </span>
                        </td>

                        <td class="status-cell">
                          <?php
                            $st = $c['status'] ?? '';
                            if ($st === 'Resolved') {
                              echo "<span class='badge bg-success badge-custom'><i class='fa fa-check-circle me-1'></i>Resolved</span>";
                            } elseif ($st === 'In Progress') {
                              echo "<span class='badge bg-info badge-custom'><i class='fa fa-spinner me-1'></i>In Progress</span>";
                            } else {
                              echo "<span class='badge bg-warning text-dark badge-custom'><i class='fa fa-clock me-1'></i>Pending</span>";
                            }
                          ?>
                        </td>

                        <td>
                          <small><i class="fa fa-calendar me-1"></i><?= date('M d, Y', strtotime($c['date_reported'])) ?></small>
                        </td>

                        <td class="resolved-cell">
                          <?php if ($c['date_resolved']): ?>
                            <small><i class="fa fa-calendar-check me-1"></i><?= date('M d, Y', strtotime($c['date_resolved'])) ?></small>
                          <?php else: ?>
                            <span class="text-muted">—</span>
                          <?php endif; ?>
                        </td>

                        <td class="handler-cell">
                          <?php if ($c['handled_by']): ?>
                            <span class="badge bg-secondary badge-custom">
                              <i class="fa fa-user me-1"></i><?= htmlspecialchars($c['handled_by']) ?>
                            </span>
                          <?php else: ?>
                            <span class="text-muted">—</span>
                          <?php endif; ?>
                        </td>

                        <td>
                          <button class="btn btn-update-status"
                            onclick="openUpdateModal(
                              <?= $c['complaint_id'] ?>,
                              '<?= addslashes($c['status'] ?? '') ?>',
                              '<?= addslashes($c['handled_by'] ?? '') ?>',
                              '<?= addslashes($c['remarks'] ?? '') ?>'
                            )">
                            <i class="fa fa-pen-to-square me-1"></i>Update
                          </button>
                        </td>

                      </tr>
                    <?php endwhile; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="8" class="text-center py-5">
                        <div class="text-muted">
                          <i class="fa fa-inbox fa-3x mb-3 d-block" style="opacity:0.3;"></i>
                          <h6>No complaints found</h6>
                          <p class="mb-0 small">All issues have been resolved or no complaints reported yet.</p>
                        </div>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div><!-- /col-lg-8 -->

      <!-- Right Column -->
      <div class="col-lg-4">

        <!-- Lost/Missing Items -->
        <div class="card-custom mb-4">
          <div class="card-header-custom">
            <h6><i class="fa fa-box-open me-2"></i>Lost/Missing Items</h6>
          </div>
          <div class="card-body-custom">
            <?php if ($lostQ && $lostQ->num_rows > 0): ?>
              <?php while ($l = $lostQ->fetch_assoc()): ?>
                <div class="lost-item">
                  <div class="lost-item-header">#<?= htmlspecialchars($l['complaint_id']) ?> — <?= htmlspecialchars($l['customer_id']) ?></div>
                  <div class="lost-item-desc"><?= htmlspecialchars(mb_strimwidth($l['issue_description'], 0, 80, '…')) ?></div>
                  <div class="d-flex justify-content-between align-items-center">
                    <?php
                      $st2 = strtolower($l['status']);
                      $bc  = $st2 === 'resolved' ? 'bg-success' : ($st2 === 'in progress' ? 'bg-info' : 'bg-warning');
                    ?>
                    <span class="badge <?= $bc ?> badge-custom"><?= htmlspecialchars($l['status']) ?></span>
                    <small class="text-muted"><i class="fa fa-calendar me-1"></i><?= date('M d', strtotime($l['date_reported'])) ?></small>
                  </div>
                </div>
              <?php endwhile; ?>
            <?php else: ?>
              <div class="text-center py-4 text-muted">
                <i class="fa fa-smile fa-2x mb-2 d-block" style="opacity:0.3;"></i>
                <small>No lost/missing items reported</small>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="card-custom">
          <div class="card-header-custom">
            <h6><i class="fa fa-bolt me-2"></i>Quick Actions</h6>
          </div>
          <div class="card-body-custom">
            <p class="small text-muted mb-3">Manage complaints efficiently with these shortcuts</p>
            <div class="d-grid gap-2">
              <a href="add_complaint.php" class="btn btn-action" style="background:var(--dark-blue);color:white;border-color:var(--dark-blue);">
                <i class="fa fa-plus-circle me-2"></i>Add New Complaint
              </a>
              <button class="btn btn-action" style="background:var(--yellow);color:var(--dark-blue);border-color:var(--yellow);" onclick="window.print();">
                <i class="fa fa-print me-2"></i>Print Report
              </button>
              <a href="export_complaints.php" class="btn btn-action" style="background:white;color:var(--dark-blue);border-color:var(--light-blue);">
                <i class="fa fa-file-export me-2"></i>Export to CSV
              </a>
            </div>

            <!-- Response Rate progress -->
            <div class="mt-4 pt-3 border-top">
              <div class="d-flex justify-content-between mb-2">
                <small class="text-muted">Response Rate</small>
                <small class="fw-bold text-success">
                  <?= $tot > 0 ? number_format((($resolved + $inprogress) / $tot) * 100, 1) : 0 ?>%
                </small>
              </div>
              <div class="progress" style="height:8px;border-radius:10px;">
                <?php $response_rate = $tot > 0 ? (($resolved + $inprogress) / $tot) * 100 : 0; ?>
                <div class="progress-bar bg-success" style="width:<?= $response_rate ?>%;border-radius:10px;"></div>
              </div>
            </div>
          </div>
        </div>

      </div><!-- /col-lg-4 -->
    </div><!-- /row -->

    <!-- ── Insights ── -->
    <div class="row g-4 mb-4">
      <div class="col-12">
        <div class="card-custom">
          <div class="card-header-custom">
            <h6><i class="fa fa-lightbulb me-2"></i>Complaint Insights</h6>
          </div>
          <div class="card-body-custom">
            <div class="row g-3">

              <div class="col-md-3">
                <div class="p-3 text-center" style="background:linear-gradient(135deg,rgba(40,167,69,0.1),rgba(32,201,151,0.1));border-radius:12px;">
                  <div class="stat-icon green mx-auto mb-2" style="width:50px;height:50px;">
                    <i class="fa fa-check-double" style="font-size:1.2rem;"></i>
                  </div>
                  <small class="text-muted d-block">Resolved This Month</small>
                  <?php
                    $resolvedThisMonth = $conn->query("
                      SELECT COUNT(*) AS c FROM complaints
                      WHERE status='Resolved'
                        AND YEAR(date_resolved)=YEAR(CURDATE())
                        AND MONTH(date_resolved)=MONTH(CURDATE())
                    ");
                    $resolvedCount = $resolvedThisMonth ? $resolvedThisMonth->fetch_assoc()['c'] : 0;
                  ?>
                  <h5 class="mb-0 mt-1"><?= $resolvedCount ?></h5>
                </div>
              </div>

              <div class="col-md-3">
                <div class="p-3 text-center" style="background:linear-gradient(135deg,rgba(255,213,91,0.2),rgba(255,224,130,0.2));border-radius:12px;">
                  <div class="stat-icon yellow mx-auto mb-2" style="width:50px;height:50px;">
                    <i class="fa fa-hourglass-half" style="font-size:1.2rem;"></i>
                  </div>
                  <small class="text-muted d-block">Pending Attention</small>
                  <h5 class="mb-0 mt-1"><?= $pending ?></h5>
                </div>
              </div>

              <div class="col-md-3">
                <div class="p-3 text-center" style="background:linear-gradient(135deg,rgba(14,165,160,0.1),rgba(23,162,184,0.1));border-radius:12px;">
                  <div class="stat-icon cyan mx-auto mb-2" style="width:50px;height:50px;">
                    <i class="fa fa-users-gear" style="font-size:1.2rem;"></i>
                  </div>
                  <small class="text-muted d-block">Being Handled</small>
                  <h5 class="mb-0 mt-1"><?= $inprogress ?></h5>
                </div>
              </div>

              <div class="col-md-3">
                <div class="p-3 text-center" style="background:linear-gradient(135deg,rgba(0,83,122,0.1),rgba(0,107,153,0.1));border-radius:12px;">
                  <div class="stat-icon blue mx-auto mb-2" style="width:50px;height:50px;">
                    <i class="fa fa-calendar-days" style="font-size:1.2rem;"></i>
                  </div>
                  <small class="text-muted d-block">Avg Resolution</small>
                  <h5 class="mb-0 mt-1"><?= $avgResolution ?> days</h5>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /container-fluid -->
</main><!-- /main -->

<!-- ════════════════════════════════════════
     UPDATE STATUS MODAL
════════════════════════════════════════ -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title text-white fw-bold" id="updateStatusModalLabel">
          <i class="fa fa-pen-to-square me-2"></i>Update Complaint Status
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="modal_complaint_id">

        <!-- Complaint badge -->
        <div class="mb-3 d-flex align-items-center gap-2">
          <span class="text-muted" style="font-size:0.82rem;font-weight:600;">Complaint</span>
          <span class="badge" id="modal_complaint_label"
            style="background:rgba(0,83,122,0.1);color:var(--dark-blue);font-size:0.85rem;padding:0.4rem 0.8rem;border-radius:8px;">#—</span>
        </div>

        <!-- Status -->
        <div class="mb-3">
          <label class="form-label">
            <i class="fa fa-tag me-1"></i>Status <span class="text-danger">*</span>
          </label>
          <select class="form-select" id="modal_status">
            <option value="Pending">⏳ Pending</option>
            <option value="In Progress">🔄 In Progress</option>
            <option value="Resolved">✅ Resolved</option>
          </select>
        </div>

        <!-- Handled By -->
        <div class="mb-3">
          <label class="form-label">
            <i class="fa fa-user-tie me-1"></i>Handled By
          </label>
          <input type="text" class="form-control" id="modal_handled_by" placeholder="Enter staff name…">
        </div>

        <!-- Remarks -->
        <div class="mb-1">
          <label class="form-label">
            <i class="fa fa-comment-dots me-1"></i>Remarks / Notes
          </label>
          <textarea class="form-control" id="modal_remarks" rows="3"
            placeholder="Add resolution notes or remarks…" style="resize:vertical;"></textarea>
        </div>

        <!-- Feedback message -->
        <div id="modal_feedback" class="mt-3" style="display:none;"></div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn"
          data-bs-dismiss="modal"
          style="border-radius:10px;border:2px solid #d0e8f0;color:var(--dark-blue);font-weight:600;padding:0.5rem 1.25rem;">
          <i class="fa fa-xmark me-1"></i>Cancel
        </button>
        <button type="button" class="btn" id="saveStatusBtn"
          style="background:linear-gradient(135deg,var(--dark-blue),#006b99);color:white;border-radius:10px;border:none;font-weight:600;padding:0.5rem 1.5rem;">
          <i class="fa fa-floppy-disk me-1"></i>Save Changes
        </button>
      </div>

    </div>
  </div>
</div>

<!-- ════════════════════════════════════════
     SCRIPTS
════════════════════════════════════════ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── Sidebar toggle ── */
const toggleBtn   = document.getElementById('toggleSidebar');
const sidebar     = document.getElementById('sidebar');
const mainContent = document.getElementById('mainContent');

toggleBtn.addEventListener('click', () => {
  sidebar.classList.toggle('collapsed');
  mainContent.classList.toggle('expanded');
});

/* ── Notification System ── */
const notificationBtn      = document.getElementById('notificationBtn');
const notificationDropdown = document.getElementById('notificationDropdown');
const notificationList     = document.getElementById('notificationList');
const notifBadge           = document.getElementById('notifBadge');
const markAllReadBtn       = document.getElementById('markAllReadBtn');
let currentFilter   = 'all';
let isDropdownOpen  = false;

notificationBtn.addEventListener('click', e => {
  e.stopPropagation();
  isDropdownOpen = !isDropdownOpen;
  notificationDropdown.classList.toggle('show', isDropdownOpen);
  if (isDropdownOpen) loadNotifications();
});

document.addEventListener('click', e => {
  if (!notificationDropdown.contains(e.target) && !notificationBtn.contains(e.target)) {
    isDropdownOpen = false;
    notificationDropdown.classList.remove('show');
  }
});

document.querySelectorAll('.notification-tab').forEach(tab => {
  tab.addEventListener('click', e => {
    document.querySelectorAll('.notification-tab').forEach(t => t.classList.remove('active'));
    e.target.classList.add('active');
    currentFilter = e.target.dataset.filter;
    loadNotifications();
  });
});

function loadNotifications() {
  const unreadOnly = currentFilter === 'unread' ? '1' : '0';
  fetch(`get_notifications.php?action=get_notifications&unread_only=${unreadOnly}&limit=15`)
    .then(r => r.json())
    .then(data => { displayNotifications(data.notifications); updateBadge(data.unread_count); })
    .catch(() => { notificationList.innerHTML = '<div class="text-center text-danger py-3">Error loading notifications</div>'; });
}

function displayNotifications(notifications) {
  if (!notifications.length) {
    notificationList.innerHTML = `
      <div class="notification-empty">
        <i class="fa fa-bell-slash"></i>
        <p class="mb-0">No notifications yet</p>
      </div>`;
    return;
  }
  notificationList.innerHTML = notifications.map(n => `
    <div class="notification-item ${n.is_read == 0 ? 'unread' : ''}"
         onclick="handleNotificationClick(${n.notification_id}, '${n.link || ''}')">
      <div class="d-flex gap-2 align-items-start">
        <div class="notification-icon ${n.type}"><i class="fa ${n.icon}"></i></div>
        <div class="notification-content">
          <div class="notification-title">${n.title}</div>
          <div class="notification-message">${n.message}</div>
          <div class="notification-time"><i class="fa fa-clock"></i><span>${n.time_ago}</span></div>
        </div>
      </div>
    </div>`).join('');
}

function handleNotificationClick(id, link) {
  fetch('get_notifications.php?action=mark_read', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `notification_id=${id}`
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      updateBadge(data.unread_count);
      if (link && link !== '#' && link !== '') window.location.href = link;
    }
  });
}

markAllReadBtn.addEventListener('click', e => {
  e.stopPropagation();
  fetch('get_notifications.php?action=mark_all_read', { method: 'POST' })
    .then(r => r.json())
    .then(data => { if (data.success) { updateBadge(0); loadNotifications(); } });
});

function updateBadge(count) {
  if (count > 0) { notifBadge.textContent = count; notifBadge.style.display = 'flex'; }
  else           { notifBadge.style.display = 'none'; }
}

setInterval(() => {
  fetch('get_notifications.php?action=get_count')
    .then(r => r.json())
    .then(data => updateBadge(data.count))
    .catch(() => {});
}, 30000);

/* ── Charts ── */
const monthLabels  = <?= json_encode($months) ?>;
const monthCounts  = <?= json_encode($monthCounts) ?>;
const statusLabels = <?= json_encode($statusLabels) ?>;
const statusCounts = <?= json_encode($statusCounts) ?>;

const commonOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { labels: { usePointStyle: true, padding: 15, font: { family: 'Poppins', size: 12, weight: '600' } } },
    tooltip: {
      backgroundColor: 'rgba(0,83,122,0.95)',
      titleFont: { family: 'Poppins', size: 13, weight: '600' },
      bodyFont:  { family: 'Poppins', size: 12 },
      padding: 12, cornerRadius: 8, displayColors: true
    }
  }
};

// Monthly bar + line chart
new Chart(document.getElementById('monthlyComplaintsChart').getContext('2d'), {
  type: 'bar',
  data: {
    labels: monthLabels,
    datasets: [
      {
        label: 'Complaints', data: monthCounts,
        backgroundColor: 'rgba(0,83,122,0.8)', borderColor: '#00537A',
        borderWidth: 2, borderRadius: 8, borderSkipped: false
      },
      {
        label: 'Trend', type: 'line', data: monthCounts,
        borderColor: '#FFD35B', backgroundColor: 'rgba(255,213,91,0.1)',
        tension: 0.4, fill: true, borderWidth: 3,
        pointBackgroundColor: '#FFD35B', pointBorderColor: '#fff',
        pointBorderWidth: 2, pointRadius: 5, pointHoverRadius: 7
      }
    ]
  },
  options: {
    ...commonOptions,
    scales: {
      y: { beginAtZero: true, precision: 0, grid: { color: 'rgba(168,232,249,0.2)', drawBorder: false }, ticks: { font: { family: 'Poppins', size: 11 }, stepSize: 1 } },
      x: { grid: { display: false }, ticks: { font: { family: 'Poppins', size: 11, weight: '500' } } }
    }
  }
});

// Status doughnut chart
new Chart(document.getElementById('statusPieChart').getContext('2d'), {
  type: 'doughnut',
  data: {
    labels: statusLabels,
    datasets: [{ data: statusCounts, backgroundColor: ['#28a745','#0ea5a0','#ffc107','#ff6b6b'], borderWidth: 3, borderColor: '#fff' }]
  },
  options: {
    ...commonOptions,
    cutout: '65%',
    plugins: {
      legend: { display: false },
      tooltip: {
        ...commonOptions.plugins.tooltip,
        callbacks: {
          label: ctx => {
            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
            return `${ctx.label}: ${ctx.parsed} (${((ctx.parsed / total) * 100).toFixed(1)}%)`;
          }
        }
      }
    }
  }
});

/* ════════════════════════════════════════
   UPDATE STATUS FEATURE
════════════════════════════════════════ */

// Open modal and prefill fields
function openUpdateModal(id, status, handledBy, remarks) {
  document.getElementById('modal_complaint_id').value    = id;
  document.getElementById('modal_complaint_label').textContent = '#' + id;
  document.getElementById('modal_status').value          = status;
  document.getElementById('modal_handled_by').value      = handledBy;
  document.getElementById('modal_remarks').value         = remarks;
  document.getElementById('modal_feedback').style.display = 'none';

  new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
}

// Save handler
document.getElementById('saveStatusBtn').addEventListener('click', function () {
  const btn         = this;
  const complaintId = document.getElementById('modal_complaint_id').value;
  const status      = document.getElementById('modal_status').value;
  const handledBy   = document.getElementById('modal_handled_by').value.trim();
  const remarks     = document.getElementById('modal_remarks').value.trim();
  const feedback    = document.getElementById('modal_feedback');

  // Loading state
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Saving…';

  const body = new URLSearchParams({ complaint_id: complaintId, status, handled_by: handledBy, remarks });

  fetch('update_complaint.php', { method: 'POST', body })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-floppy-disk me-1"></i>Save Changes';

      if (data.success) {
        feedback.style.display = 'block';
        feedback.innerHTML = `
          <div class="alert alert-success py-2 mb-0" style="border-radius:10px;font-size:0.88rem;">
            <i class="fa fa-check-circle me-1"></i>${data.message}
          </div>`;

        updateTableRow(data);

        setTimeout(() => {
          bootstrap.Modal.getInstance(document.getElementById('updateStatusModal')).hide();
        }, 1200);
      } else {
        feedback.style.display = 'block';
        feedback.innerHTML = `
          <div class="alert alert-danger py-2 mb-0" style="border-radius:10px;font-size:0.88rem;">
            <i class="fa fa-circle-exclamation me-1"></i>${data.message}
          </div>`;
      }
    })
    .catch(() => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-floppy-disk me-1"></i>Save Changes';
      feedback.style.display = 'block';
      feedback.innerHTML = `
        <div class="alert alert-danger py-2 mb-0" style="border-radius:10px;font-size:0.88rem;">
          <i class="fa fa-circle-exclamation me-1"></i>Network error. Please try again.
        </div>`;
    });
});

// Live-update the table row without page reload
function updateTableRow(data) {
  const row = document.getElementById('row-' + data.complaint_id);
  if (!row) return;

  // Status badge
  const statusCell = row.querySelector('.status-cell');
  if (statusCell) {
    const badgeMap = {
      'Resolved':    `<span class='badge bg-success badge-custom'><i class='fa fa-check-circle me-1'></i>Resolved</span>`,
      'In Progress': `<span class='badge bg-info badge-custom'><i class='fa fa-spinner me-1'></i>In Progress</span>`,
      'Pending':     `<span class='badge bg-warning text-dark badge-custom'><i class='fa fa-clock me-1'></i>Pending</span>`,
    };
    statusCell.innerHTML = badgeMap[data.status] || badgeMap['Pending'];
  }

  // Resolved date
  const resolvedCell = row.querySelector('.resolved-cell');
  if (resolvedCell) {
    resolvedCell.innerHTML = data.date_resolved
      ? `<small><i class='fa fa-calendar-check me-1'></i>${data.date_resolved}</small>`
      : `<span class='text-muted'>—</span>`;
  }

  // Handler
  const handlerCell = row.querySelector('.handler-cell');
  if (handlerCell) {
    handlerCell.innerHTML = data.handled_by
      ? `<span class='badge bg-secondary badge-custom'><i class='fa fa-user me-1'></i>${data.handled_by}</span>`
      : `<span class='text-muted'>—</span>`;
  }

  // Refresh onclick args on Update button
  const btn = row.querySelector('.btn-update-status');
  if (btn) {
    const esc = s => (s || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    btn.setAttribute('onclick',
      `openUpdateModal(${data.complaint_id},'${esc(data.status)}','${esc(data.handled_by)}','${esc(data.remarks)}')`
    );
  }

  // Green flash animation
  row.classList.remove('row-flash');
  void row.offsetWidth; // reflow to restart animation
  row.classList.add('row-flash');
}
</script>
</body>
</html>
<?php $conn->close(); ?>