<?php
// dashboard.php — MangTV Admin Dashboard with Notification System
session_start();
include 'db_connect.php';

// If the login process sets a session flag like $_SESSION['login_success']=true,
// we will show a welcome modal once and then unset the flag.
$showWelcomeModal = false;
$adminDisplayName = 'Admin';
if (!empty($_SESSION['login_success'])) {
    $showWelcomeModal = true;
    // Optionally use a session username if available
    if (!empty($_SESSION['username'])) {
        $adminDisplayName = $_SESSION['username'];
    }
    // Unset so modal only shows once
    unset($_SESSION['login_success']);
}

// Include notification helpers if file exists
if (file_exists('notification_helpers.php')) {
    include 'notification_helpers.php';
    $unreadNotifCount = getUnreadCount($conn);
} else {
    $unreadNotifCount = 0; // Default if notification system not set up yet
}

// ---------- SUMMARY METRICS ----------
$totalSchedules = (int) ($conn->query("SELECT COUNT(*) AS c FROM schedule")->fetch_assoc()['c'] ?? 0);
$totalEmployees = (int) ($conn->query("SELECT COUNT(*) AS c FROM employees")->fetch_assoc()['c'] ?? 0);

// Total income from transaction table (sum payment)
$totalIncome = (float) ($conn->query("SELECT IFNULL(SUM(payment),0) AS s FROM `transaction`")->fetch_assoc()['s'] ?? 0);

// Pending complaints
$pendingComplaints = (int) ($conn->query("SELECT COUNT(*) AS c FROM complaints WHERE status NOT IN ('Resolved')")->fetch_assoc()['c'] ?? 0);

// ---------- MONTHLY DATA (CURRENT YEAR) ----------
// Monthly income (Jan..Dec)
$monthsLabels = [];
$incomeByMonth = [];
for ($m = 1; $m <= 12; $m++) {
    $label = date('M', mktime(0,0,0,$m,1));
    $monthsLabels[] = $label;
    $q = $conn->query("SELECT IFNULL(SUM(payment),0) AS s FROM `transaction` WHERE YEAR(transaction_date)=YEAR(CURDATE()) AND MONTH(transaction_date)=$m");
    $incomeByMonth[] = (float)($q->fetch_assoc()['s'] ?? 0);
}

// Monthly schedules count (Jan..Dec)
$schedulesByMonth = [];
for ($m = 1; $m <= 12; $m++) {
    $q = $conn->query("SELECT COUNT(*) AS c FROM schedule WHERE YEAR(date)=YEAR(CURDATE()) AND MONTH(date)=$m");
    $schedulesByMonth[] = (int)($q->fetch_assoc()['c'] ?? 0);
}

// ---------- RECENT LISTS ----------
$recentTransactions = $conn->query("
  SELECT transaction_id, customer_id, payment, payment_status, transaction_date
  FROM `transaction`
  ORDER BY transaction_date DESC
  LIMIT 10
");

$recentSchedules = $conn->query("
  SELECT Schedule_ID, Customer_ID, date, time, service, add_ons, pick_deliver, admin_confirmation
  FROM schedule
  ORDER BY date DESC, time DESC
  LIMIT 6
");

$recentComplaints = $conn->query("
  SELECT complaint_id, customer_id, issue_description, status, date_reported
  FROM complaints
  ORDER BY date_reported DESC
  LIMIT 5
");

// ---------- MOST FREQUENT CUSTOMER ----------
$topCustomerQ = $conn->query("
  SELECT customer_id, COUNT(*) AS c
  FROM `transaction`
  GROUP BY customer_id
  ORDER BY c DESC
  LIMIT 1
");
$topCustomer = $topCustomerQ && $topCustomerQ->num_rows ? $topCustomerQ->fetch_assoc() : null;

// ---------- Handle schedule confirmation (Approve/Reject) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_action'], $_POST['schedule_id'])) {
    $sid = (int) $_POST['schedule_id'];
    $new = $conn->real_escape_string($_POST['confirm_action']);
    $stmt = $conn->prepare("UPDATE schedule SET admin_confirmation = ? WHERE Schedule_ID = ?");
    $stmt->bind_param("si", $new, $sid);
    $stmt->execute();
    // redirect to avoid resubmission
    header("Location: dashboard.php");
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>MangTV Admin Dashboard</title>

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
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body { 
      font-family: 'Poppins', sans-serif; 
      background: var(--bg-light); 
      color: var(--text-dark);
      overflow-x: hidden;
    }
    
    /* Sidebar Styles */
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
    
    .sidebar::-webkit-scrollbar {
      width: 6px;
    }
    
    .sidebar::-webkit-scrollbar-track {
      background: rgba(255,255,255,0.1);
    }
    
    .sidebar::-webkit-scrollbar-thumb {
      background: rgba(255,255,255,0.3);
      border-radius: 3px;
    }
    
    .sidebar.collapsed { 
      width: var(--sidebar-collapsed); 
    }
    
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
    
    .brand-logo i {
      font-size: 1.5rem;
      color: var(--dark-blue);
    }
    
    .brand-text {
      transition: all 0.3s;
    }
    
    .brand-text h4 {
      margin: 0;
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--yellow);
    }
    
    .brand-text p {
      margin: 0;
      font-size: 0.75rem;
      opacity: 0.8;
    }
    
    .sidebar-nav {
      padding: 1.5rem 0;
    }
    
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

    /* Main Content */
    main { 
      margin-left: var(--sidebar-width); 
      transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      min-height: 100vh;
      background: var(--bg-light);
    }
    
    main.expanded { 
      margin-left: var(--sidebar-collapsed); 
    }

    /* Topbar */
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
    
    .topbar-icon-wrapper {
      position: relative;
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
    
    /* Notification Dropdown */
    .notification-dropdown {
      position: absolute;
      top: calc(100% + 10px);
      right: 0;
      width: 400px;
      max-height: 580px;
      background: white;
      border-radius: 12px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.12);
      opacity: 0;
      visibility: hidden;
      transform: translateY(-10px);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      z-index: 9999;
      overflow: visible;
      border: 1px solid #e0e0e0;
      display: flex;
      flex-direction: column;
    }
    
    .notification-dropdown.show {
      opacity: 1;
      visibility: visible;
      transform: translateY(0);
    }
    
    .notification-header {
      padding: 1rem 1.25rem;
      background: white;
      color: var(--dark-blue);
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid rgba(168,232,249,0.2);
    }
    
    .notification-header h6 {
      margin: 0;
      font-size: 1rem;
      font-weight: 700;
      color: var(--dark-blue);
    }
    
    .notification-tabs {
      display: flex;
      gap: 0.5rem;
      padding: 0.75rem 1.25rem;
      background: white;
      border-bottom: 1px solid rgba(168,232,249,0.2);
    }
    
    .notification-tab {
      padding: 0.4rem 1rem;
      border-radius: 16px;
      font-size: 0.8rem;
      font-weight: 600;
      background: transparent;
      color: #6c757d;
      border: none;
      cursor: pointer;
      transition: all 0.3s;
    }
    
    .notification-tab.active {
      background: var(--dark-blue);
      color: white;
      box-shadow: 0 2px 6px rgba(0,83,122,0.2);
    }
    
    .notification-list {
      max-height: 350px;
      overflow-y: auto;
      padding: 0.5rem 0.75rem;
      background: var(--bg-light);
    }
    
    .notification-list::-webkit-scrollbar {
      width: 4px;
    }
    
    .notification-list::-webkit-scrollbar-track {
      background: transparent;
    }
    
    .notification-list::-webkit-scrollbar-thumb {
      background: rgba(168,232,249,0.5);
      border-radius: 10px;
    }
    
    .notification-list::-webkit-scrollbar-thumb:hover {
      background: var(--light-blue);
    }
    
    .notification-item {
      padding: 1rem;
      border-radius: 12px;
      margin-bottom: 0.5rem;
      background: white;
      border: 1px solid rgba(168,232,249,0.15);
      cursor: pointer;
      transition: all 0.3s;
      position: relative;
      box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    
    .notification-item:hover {
      background: white;
      border-color: var(--light-blue);
      transform: translateX(2px);
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .notification-item.unread {
      background: white;
      border-color: var(--dark-blue);
      border-left-width: 3px;
    }
    
    .notification-item.unread::before {
      display: none;
    }
    
    .notification-icon {
      width: 45px;
      height: 45px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      flex-shrink: 0;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    
    .notification-icon.new-customer {
      background: linear-gradient(135deg, #28a745 0%, #34ce57 100%);
      color: white;
    }
    
    .notification-icon.new-schedule, .notification-icon.new_schedule {
      background: linear-gradient(135deg, var(--dark-blue) 0%, #0077b6 100%);
      color: white;
    }
    
    .notification-icon.new-complaint, .notification-icon.new_complaint {
      background: linear-gradient(135deg, #dc3545 0%, #ff6b6b 100%);
      color: white;
    }
    
    .notification-icon.new-feedback, .notification-icon.new_feedback {
      background: linear-gradient(135deg, #ffc107 0%, #ffd93d 100%);
      color: var(--dark-blue);
    }
    
    .notification-icon.payment-received, .notification-icon.payment_received {
      background: linear-gradient(135deg, #198754 0%, #51cf66 100%);
      color: white;
    }
    
    .notification-icon.schedule-updated, .notification-icon.schedule_updated {
      background: linear-gradient(135deg, #0dcaf0 0%, #4dabf7 100%);
      color: white;
    }
    
    .notification-content {
      flex: 1;
      min-width: 0;
    }
    
    .notification-title {
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--dark-blue);
      margin-bottom: 0.3rem;
      line-height: 1.3;
    }
    
    .notification-message {
      font-size: 0.8rem;
      color: #6c757d;
      margin-bottom: 0.5rem;
      line-height: 1.4;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    
    .notification-time {
      font-size: 0.7rem;
      color: #adb5bd;
      display: flex;
      align-items: center;
      gap: 0.25rem;
    }
    
    .notification-time i {
      font-size: 0.65rem;
    }
    
    .notification-footer {
      padding: 0.75rem 1.25rem;
      background: white;
      text-align: center;
      border-top: 1px solid rgba(168,232,249,0.2);
    }
    
    .notification-empty {
      padding: 3rem 1rem;
      text-align: center;
      color: #6c757d;
    }
    
    .notification-empty i {
      font-size: 2.5rem;
      color: rgba(168,232,249,0.6);
      margin-bottom: 0.75rem;
      opacity: 0.5;
    }
    
    .notification-empty p {
      font-size: 0.85rem;
      font-weight: 500;
      color: #adb5bd;
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
    }
    
    .btn-logout:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(0,83,122,0.3);
      color: white;
    }
    
    /* Stats Cards */
    .stat-card { 
      background: white;
      border-radius: 16px;
      padding: 1.5rem;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
      border: 1px solid rgba(168,232,249,0.2);
      transition: all 0.3s;
      height: 100%;
      cursor: pointer;
      text-decoration: none;
      display: block;
      color: inherit;
    }
    
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 24px rgba(0,0,0,0.1);
      text-decoration: none;
      color: inherit;
    }
    
    .stat-card-content {
      display: flex;
      gap: 1.25rem;
      align-items: center;
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
    
    .stat-icon.blue { 
      background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
    }
    
    .stat-icon.green { 
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    }
    
    .stat-icon.success { 
      background: linear-gradient(135deg, #198754 0%, #20c997 100%);
    }
    
    .stat-icon.yellow { 
      background: linear-gradient(135deg, var(--yellow) 0%, #ffe082 100%);
      color: var(--dark-blue);
    }
    
    .stat-info {
      flex: 1;
    }
    
    .stat-label {
      color: #6c757d;
      font-size: 0.85rem;
      font-weight: 500;
      margin-bottom: 0.25rem;
    }
    
    .stat-value {
      font-size: 1.75rem;
      font-weight: 700;
      color: var(--dark-blue);
      line-height: 1;
    }
    
    .stat-value.success {
      color: #198754;
    }
    
    /* Cards */
    .card-custom { 
      background: white;
      border-radius: 16px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
      border: 1px solid rgba(168,232,249,0.2);
      overflow: hidden;
    }
    
    .card-header-custom {
      background: linear-gradient(135deg, var(--light-blue) 0%, rgba(168,232,249,0.3) 100%);
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid rgba(168,232,249,0.3);
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
    
    .card-body-custom {
      padding: 1.5rem;
    }
    
    /* Tables */
    .table-custom {
      margin: 0;
    }
    
    .table-custom thead th { 
      background: var(--yellow);
      color: var(--dark-blue);
      font-weight: 600;
      font-size: 0.9rem;
      padding: 1rem;
      border: none;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .table-custom tbody tr {
      transition: all 0.3s;
    }
    
    .table-custom tbody tr:hover {
      background: rgba(168,232,249,0.1);
    }
    
    .table-custom tbody td {
      padding: 1rem;
      vertical-align: middle;
      border-bottom: 1px solid rgba(168,232,249,0.2);
      font-size: 0.9rem;
    }
    
    /* Badges */
    .badge-custom {
      padding: 0.4rem 0.75rem;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.75rem;
      letter-spacing: 0.5px;
    }
    
    /* Schedule & Complaint Items */
    .list-item {
      padding: 1rem;
      border-radius: 12px;
      background: var(--bg-light);
      margin-bottom: 1rem;
      transition: all 0.3s;
      border: 1px solid rgba(168,232,249,0.3);
      cursor: pointer;
      text-decoration: none;
      display: block;
      color: inherit;
    }
    
    .list-item:hover {
      background: white;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      text-decoration: none;
      color: inherit;
      transform: translateX(5px);
    }
    
    .list-item-header {
      font-weight: 600;
      color: var(--dark-blue);
      margin-bottom: 0.5rem;
    }
    
    .list-item-meta {
      font-size: 0.85rem;
      color: #6c757d;
      margin-bottom: 0.25rem;
    }
    
    /* Top Customer Card */
    .top-customer-card {
      background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
      color: white;
      border-radius: 16px;
      padding: 1.5rem;
      text-align: center;
      box-shadow: 0 8px 24px rgba(0,83,122,0.2);
    }
    
    .top-customer-icon {
      width: 60px;
      height: 60px;
      background: var(--yellow);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1rem;
      font-size: 1.5rem;
      color: var(--dark-blue);
    }
    
    /* Footer */
    footer { 
      background: white;
      padding: 1.5rem 0;
      margin-top: 3rem;
      text-align: center;
      color: #6c757d;
      border-top: 1px solid rgba(168,232,249,0.3);
      font-size: 0.9rem;
    }
    
    /* Responsive */
    @media (max-width:992px) {
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
      
      .notification-dropdown {
        width: 90vw;
        right: -150px;
      }
    }
    
    @media (max-width:768px) {
      .topbar {
        padding: 1rem;
      }
      
      .stat-card {
        margin-bottom: 1rem;
      }
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="brand-logo">
        <i class="fas fa-tshirt"></i>
      </div>
      <div class="brand-text">
        <h4>MangTV Laundry Shop</h4>
        <p>Admin Dashboard</p>
      </div>
    </div>
    
    <div class="sidebar-nav">
      <div class="nav-section-title">Main Menu</div>
      <ul class="nav flex-column">
        <li class="nav-item">
          <a href="dashboard.php" class="nav-link active">
            <i class="fa fa-chart-line"></i>
            <span class="nav-text">Dashboard</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="customer_database.php" class="nav-link">
            <i class="fa fa-users"></i>
            <span class="nav-text">Customers</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="digital_record.php" class="nav-link">
            <i class="fa fa-database"></i>
            <span class="nav-text">Records</span>
          </a>
        </li>
      </ul>
      
      <div class="nav-section-title">Operations</div>
      <ul class="nav flex-column">
        <li class="nav-item">
          <a href="order_scheduling.php" class="nav-link">
            <i class="fa fa-calendar-check"></i>
            <span class="nav-text">Schedules</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="walkin.php" class="nav-link">
            <i class="fa fa-person-walking"></i>
            <span class="nav-text">Walk-in</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="payments.php" class="nav-link">
            <i class="fa fa-credit-card"></i>
            <span class="nav-text">Payments</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="financials.php" class="nav-link">
            <i class="fa fa-chart-pie"></i>
            <span class="nav-text">Financials</span>
          </a>
        </li>
      </ul>

      <div class="nav-section-title">Inventory</div>
      <ul class="nav flex-column">
        <li class="nav-item"><a href="inventory.php" class="nav-link"><i class="fa fa-boxes"></i><span class="nav-text">Inventory</span></a></li>
        <li class="nav-item"><a href="suppliers.php" class="nav-link"><i class="fa fa-truck"></i><span class="nav-text">Suppliers</span></a></li>
        <li class="nav-item"><a href="purchase_orders.php" class="nav-link"><i class="fa fa-shopping-cart"></i><span class="nav-text">Purchase Orders</span></a></li>
      </ul>
      
      <div class="nav-section-title">Support</div>
      <ul class="nav flex-column">
        <li class="nav-item">
          <a href="complaints.php" class="nav-link">
            <i class="fa fa-exclamation-circle"></i>
            <span class="nav-text">Complaints</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="employees.php" class="nav-link">
            <i class="fa fa-user-tie"></i>
            <span class="nav-text">Employees</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="feedback.php" class="nav-link">
            <i class="fa fa-comments"></i>
            <span class="nav-text">Feedback</span>
          </a>
        </li>
      </ul>
      
      <div class="nav-section-title">Account</div>
      <ul class="nav flex-column">
        <li class="nav-item">
          <a href="logout.php" class="nav-link">
            <i class="fa fa-right-from-bracket"></i>
            <span class="nav-text">Logout</span>
          </a>
        </li>
      </ul>
    </div>
  </nav>

  <!-- Main Content -->
  <main id="mainContent">
    <!-- Topbar -->
    <div class="topbar">
      <div class="d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
          <button class="toggle-btn" id="toggleSidebar">
            <i class="fa fa-bars"></i>
          </button>
          <div class="topbar-title">
            <h5>Dashboard Overview</h5>
            <small>Monitor your laundry business performance</small>
          </div>
        </div>
        <div class="topbar-actions">
          <div class="topbar-icon-wrapper">
            <button class="topbar-icon" id="notificationBtn">
              <i class="fa fa-bell"></i>
              <span class="badge bg-danger" id="notifBadge" style="<?= $unreadNotifCount > 0 ? '' : 'display:none;' ?>">
                <?= $unreadNotifCount ?>
              </span>
            </button>
            
            <!-- Notification Dropdown -->
            <div class="notification-dropdown" id="notificationDropdown">
              <div class="notification-header">
                <h6><i class="fa fa-bell me-2"></i>Notifications</h6>
                <button class="btn btn-sm" id="markAllReadBtn" style="font-size: 0.7rem; padding: 0.35rem 0.85rem; border-radius: 6px; background: var(--dark-blue); color: white; border: none; font-weight: 600;">
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
                    <span class="visually-hidden">Loading...</span>
                  </div>
                </div>
              </div>
              
              <div class="notification-footer">
                <small class="text-muted" style="font-size: 0.7rem;">
                  <i class="fa fa-info-circle me-1"></i>
                  Tap notification to view details
                </small>
              </div>
            </div>
          </div>
          
          <a href="logout.php" class="btn btn-logout">
            <i class="fa fa-right-from-bracket me-2"></i>Logout
          </a>
        </div>
      </div>
    </div>

    <div class="container-fluid py-4 px-4">
      <!-- Summary Cards -->
      <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
          <a href="order_scheduling.php" class="stat-card">
            <div class="stat-card-content">
              <div class="stat-icon blue">
                <i class="fa fa-calendar-check"></i>
              </div>
              <div class="stat-info">
                <div class="stat-label">Total Schedules</div>
                <div class="stat-value"><?= number_format($totalSchedules) ?></div>
              </div>
            </div>
          </a>
        </div>

        <div class="col-xl-3 col-md-6">
          <a href="employees.php" class="stat-card">
            <div class="stat-card-content">
              <div class="stat-icon green">
                <i class="fa fa-user-tie"></i>
              </div>
              <div class="stat-info">
                <div class="stat-label">Total Employees</div>
                <div class="stat-value"><?= number_format($totalEmployees) ?></div>
              </div>
            </div>
          </a>
        </div>

        <div class="col-xl-3 col-md-6">
          <a href="financials.php" class="stat-card">
            <div class="stat-card-content">
              <div class="stat-icon success">
                <i class="fa fa-coins"></i>
              </div>
              <div class="stat-info">
                <div class="stat-label">Total Income</div>
                <div class="stat-value success">₱<?= number_format($totalIncome,2) ?></div>
              </div>
            </div>
          </a>
        </div>

        <div class="col-xl-3 col-md-6">
          <a href="complaints.php" class="stat-card">
            <div class="stat-card-content">
              <div class="stat-icon yellow">
                <i class="fa fa-exclamation-triangle"></i>
              </div>
              <div class="stat-info">
                <div class="stat-label">Pending Complaints</div>
                <div class="stat-value"><?= number_format($pendingComplaints) ?></div>
              </div>
            </div>
          </a>
        </div>
      </div>

      <!-- Charts Row -->
      <div class="row g-4 mb-4">
        <div class="col-lg-7">
          <div class="card-custom">
            <div class="card-header-custom">
              <h6><i class="fa fa-chart-bar me-2"></i>Monthly Income (This Year)</h6>
            </div>
            <div class="card-body-custom">
              <div style="height:320px;">
                <canvas id="monthlyIncomeChart"></canvas>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="card-custom">
            <div class="card-header-custom">
              <h6><i class="fa fa-calendar-alt me-2"></i>Schedules Per Month</h6>
            </div>
            <div class="card-body-custom">
              <div style="height:320px;">
                <canvas id="schedulesChart"></canvas>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Content Row -->
      <div class="row g-4">
        <div class="col-lg-8">
          <div class="card-custom">
            <div class="card-header-custom">
              <div class="d-flex justify-content-between align-items-center">
                <h6><i class="fa fa-receipt me-2"></i>Recent Transactions</h6>
                <a href="payments.php" class="btn btn-sm" style="background: var(--dark-blue); color: white; border-radius: 8px;">
                  View All <i class="fa fa-arrow-right ms-1"></i>
                </a>
              </div>
            </div>
            <div class="card-body-custom p-0">
              <div class="table-responsive">
                <table class="table table-custom">
                  <thead>
                    <tr>
                      <th>Transaction ID</th>
                      <th>Customer ID</th>
                      <th>Payment</th>
                      <th>Status</th>
                      <th>Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($recentTransactions && $recentTransactions->num_rows > 0): ?>
                      <?php while ($t = $recentTransactions->fetch_assoc()): ?>
                        <tr>
                          <td><strong><?= htmlspecialchars($t['transaction_id']) ?></strong></td>
                          <td><?= htmlspecialchars($t['customer_id']) ?></td>
                          <td><strong>₱<?= number_format($t['payment'],2) ?></strong></td>
                          <td>
                            <?php if (strtolower($t['payment_status']) === 'paid'): ?>
                              <span class="badge bg-success badge-custom">Paid</span>
                            <?php else: ?>
                              <span class="badge bg-warning badge-custom"><?= htmlspecialchars($t['payment_status']) ?></span>
                            <?php endif; ?>
                          </td>
                          <td><?= htmlspecialchars($t['transaction_date']) ?></td>
                        </tr>
                      <?php endwhile; ?>
                    <?php else: ?>
                      <tr><td colspan="5" class="text-muted text-center py-4">No recent transactions.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column: Recent Schedules & Complaints -->
        <div class="col-lg-4">
          <!-- Recent Schedules -->
          <div class="card-custom mb-4">
            <div class="card-header-custom">
              <div class="d-flex justify-content-between align-items-center">
                <h6><i class="fa fa-calendar-check me-2"></i>Recent Schedules</h6>
                <a href="order_scheduling.php" class="btn btn-sm" style="background: var(--dark-blue); color: white; border-radius: 8px;">
                  View All <i class="fa fa-arrow-right ms-1"></i>
                </a>
              </div>
            </div>
            <div class="card-body-custom">
              <?php if ($recentSchedules && $recentSchedules->num_rows > 0): ?>
                <?php while ($s = $recentSchedules->fetch_assoc()): ?>
                  <a href="order_scheduling.php?id=<?= (int)$s['Schedule_ID'] ?>" class="list-item">
                    <div class="d-flex justify-content-between align-items-start">
                      <div class="flex-grow-1">
                        <div class="list-item-header">
                          #<?= htmlspecialchars($s['Schedule_ID']) ?> — <?= htmlspecialchars($s['Customer_ID']) ?>
                        </div>
                        <div class="list-item-meta">
                          <i class="fa fa-calendar me-1"></i><?= htmlspecialchars($s['date']) ?> · 
                          <i class="fa fa-clock ms-2 me-1"></i><?= htmlspecialchars($s['time']) ?>
                        </div>
                        <div class="list-item-meta">
                          <i class="fa fa-tags me-1"></i><?= htmlspecialchars(mb_strimwidth($s['service'],0,30,'...')) ?>
                        </div>
                      </div>
                      <div class="text-end ms-2">
                        <?php 
                          $st = htmlspecialchars($s['admin_confirmation']);
                          if ($st === 'Approved') {
                            $cls = 'bg-success';
                          } elseif ($st === 'Rejected') {
                            $cls = 'bg-danger';
                          } elseif ($st === 'Pending') {
                            $cls = 'bg-warning';
                          } else {
                            $cls = 'bg-secondary';
                          }
                          echo "<span class='badge $cls badge-custom'>$st</span>";
                        ?>
                      </div>
                    </div>
                  </a>
                <?php endwhile; ?>
              <?php else: ?>
                <div class="text-muted text-center py-3">No schedules yet.</div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Recent Complaints -->
          <div class="card-custom mb-4">
            <div class="card-header-custom">
              <div class="d-flex justify-content-between align-items-center">
                <h6><i class="fa fa-exclamation-circle me-2"></i>Recent Complaints</h6>
                <a href="complaints.php" class="btn btn-sm" style="background: var(--dark-blue); color: white; border-radius: 8px;">
                  View All <i class="fa fa-arrow-right ms-1"></i>
                </a>
              </div>
            </div>
            <div class="card-body-custom">
              <?php if ($recentComplaints && $recentComplaints->num_rows > 0): ?>
                <?php while ($c = $recentComplaints->fetch_assoc()): ?>
                  <a href="complaints.php?id=<?= (int)$c['complaint_id'] ?>" class="list-item">
                    <div class="list-item-header">
                      #<?= htmlspecialchars($c['complaint_id']) ?> — <?= htmlspecialchars($c['customer_id']) ?>
                    </div>
                    <div class="list-item-meta mb-2">
                      <?= htmlspecialchars(mb_strimwidth($c['issue_description'],0,60,'...')) ?>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                      <?php 
                        $st = $c['status'];
                        $b = $st === 'Resolved' ? 'bg-success' : ($st === 'In Progress' ? 'bg-info' : 'bg-warning');
                        echo "<span class='badge $b badge-custom'>$st</span>";
                      ?>
                      <small class="text-muted">
                        <i class="fa fa-calendar me-1"></i><?= htmlspecialchars($c['date_reported']) ?>
                      </small>
                    </div>
                  </a>
                <?php endwhile; ?>
              <?php else: ?>
                <div class="text-muted text-center py-3">No complaints found.</div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Top Customer -->
          <?php if ($topCustomer): ?>
            <div class="top-customer-card">
              <div class="top-customer-icon">
                <i class="fa fa-trophy"></i>
              </div>
              <div class="text-muted small mb-2">Most Frequent Customer</div>
              <div class="h4 fw-bold mb-2"><?= htmlspecialchars($topCustomer['customer_id']) ?></div>
              <div class="small">
                <i class="fa fa-shopping-bag me-1"></i>
                <?= (int)$topCustomer['c'] ?> Transactions
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <footer>
        <p class="mb-0">© <?= date('Y') ?> <strong>MangTV Laundry Shop</strong> - All Rights Reserved</p>
      </footer>
    </div>
  </main>

  
  <!-- Welcome Modal (shown once after successful login) -->
  <div class="modal fade" id="welcomeModal" tabindex="-1" aria-labelledby="welcomeModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content" style="border: none; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.15);">
        <div class="modal-header" style="border-bottom: none; padding: 2rem 2rem 1rem; background: linear-gradient(135deg, var(--dark-blue) 0%, #69b9dbff 100%); position: relative;">
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="position: absolute; top: 1rem; right: 1rem;"></button>
        </div>
        <div class="modal-body text-center" style="padding: 2rem 2.5rem 2.5rem; background: linear-gradient(135deg, var(--dark-blue) 0%, #73b5d1ff 100%);">
          <div style="width: 80px; height: 80px; background: var(--yellow); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; box-shadow: 0 8px 24px rgba(255,213,91,0.4);">
            <i class="fa fa-tshirt" style="font-size: 2.5rem; color: var(--dark-blue);"></i>
          </div>
          <h3 style="color: white; font-weight: 700; margin-bottom: 0.5rem; font-size: 1.75rem;">Welcome to Admin Dashboard</h3>
          <p style="color: rgba(255,255,255,0.85); margin-bottom: 0; font-size: 0.95rem;">MangTV Laundry Shop Management System</p>
        </div>
        
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // ========== SIDEBAR TOGGLE ==========
    const toggleBtn = document.getElementById('toggleSidebar');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    
    toggleBtn.addEventListener('click', () => {
      sidebar.classList.toggle('collapsed');
      mainContent.classList.toggle('expanded');
    });

    // ========== NOTIFICATION SYSTEM ==========
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const notificationList = document.getElementById('notificationList');
    const notifBadge = document.getElementById('notifBadge');
    const markAllReadBtn = document.getElementById('markAllReadBtn');
    
    let currentFilter = 'all';
    let isDropdownOpen = false;
    
    // Toggle dropdown
    notificationBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      isDropdownOpen = !isDropdownOpen;
      notificationDropdown.classList.toggle('show', isDropdownOpen);
      
      if (isDropdownOpen) {
        loadNotifications();
      }
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
      if (!notificationDropdown.contains(e.target) && !notificationBtn.contains(e.target)) {
        isDropdownOpen = false;
        notificationDropdown.classList.remove('show');
      }
    });
    
    // Tab filtering
    document.querySelectorAll('.notification-tab').forEach(tab => {
      tab.addEventListener('click', (e) => {
        document.querySelectorAll('.notification-tab').forEach(t => t.classList.remove('active'));
        e.target.classList.add('active');
        currentFilter = e.target.dataset.filter;
        loadNotifications();
      });
    });
    
    // Load notifications via AJAX
    function loadNotifications() {
      const unreadOnly = currentFilter === 'unread' ? '1' : '0';
      
      fetch(`get_notifications.php?action=get_notifications&unread_only=${unreadOnly}&limit=15`)
        .then(response => response.json())
        .then(data => {
          displayNotifications(data.notifications);
          updateBadge(data.unread_count);
        })
        .catch(error => {
          console.error('Error loading notifications:', error);
          notificationList.innerHTML = '<div class="text-center text-danger py-3">Error loading notifications</div>';
        });
    }
    
    // Display notifications
    function displayNotifications(notifications) {
      if (notifications.length === 0) {
        notificationList.innerHTML = `
          <div class="notification-empty">
            <i class="fa fa-bell-slash"></i>
            <p class="mb-0">No notifications yet</p>
          </div>
        `;
        return;
      }
      
      notificationList.innerHTML = notifications.map(notif => `
        <div class="notification-item ${notif.is_read == 0 ? 'unread' : ''}" 
             data-id="${notif.notification_id}"
             data-link="${notif.link || '#'}"
             onclick="handleNotificationClick(${notif.notification_id}, '${notif.link || ''}')">
          <div class="d-flex gap-2 align-items-start">
            <div class="notification-icon ${notif.type}">
              <i class="fa ${notif.icon}"></i>
            </div>
            <div class="notification-content">
              <div class="notification-title">${notif.title}</div>
              <div class="notification-message">${notif.message}</div>
              <div class="notification-time">
                <i class="fa fa-clock"></i>
                <span>${notif.time_ago}</span>
              </div>
            </div>
          </div>
        </div>
      `).join('');
    }
    
    // Handle notification click
    function handleNotificationClick(notificationId, link) {
      // Mark as read
      fetch('get_notifications.php?action=mark_read', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `notification_id=${notificationId}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          updateBadge(data.unread_count);
          // Redirect to the link
          if (link && link !== '#' && link !== '') {
            window.location.href = link;
          }
        }
      })
      .catch(error => console.error('Error marking as read:', error));
    }
    
    // Mark all as read
    markAllReadBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      
      fetch('get_notifications.php?action=mark_all_read', {
        method: 'POST'
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          updateBadge(0);
          loadNotifications();
        }
      })
      .catch(error => console.error('Error marking all as read:', error));
    });
    
    // Update badge count
    function updateBadge(count) {
      if (count > 0) {
        notifBadge.textContent = count;
        notifBadge.style.display = 'flex';
      } else {
        notifBadge.style.display = 'none';
      }
    }
    
    // Poll for new notifications every 30 seconds
    function pollNotifications() {
      fetch('get_notifications.php?action=get_count')
        .then(response => response.json())
        .then(data => {
          updateBadge(data.count);
        })
        .catch(error => console.error('Error polling notifications:', error));
    }
    
    // Start polling
    setInterval(pollNotifications, 30000); // Every 30 seconds

    // ========== CHARTS ==========
    const monthLabels = <?= json_encode($monthsLabels) ?>;
    const incomeData = <?= json_encode($incomeByMonth) ?>;
    const schedulesData = <?= json_encode($schedulesByMonth) ?>;

    // Destroy existing charts if any
    let monthlyChart, schedulesChart;
    const mCtx = document.getElementById('monthlyIncomeChart').getContext('2d');
    const sCtx = document.getElementById('schedulesChart').getContext('2d');

    if (monthlyChart) monthlyChart.destroy();
    if (schedulesChart) schedulesChart.destroy();

    // Monthly Income Chart (Bar + Line)
    monthlyChart = new Chart(mCtx, {
      type: 'bar',
      data: {
        labels: monthLabels,
        datasets: [
          {
            label: 'Income (₱)',
            data: incomeData,
            backgroundColor: 'rgba(255,213,91,0.8)',
            borderColor: '#FFD35B',
            borderWidth: 2,
            borderRadius: 8,
            borderSkipped: false
          },
          {
            label: 'Trend',
            type: 'line',
            data: incomeData,
            borderColor: '#00537A',
            backgroundColor: 'rgba(0,83,122,0.1)',
            tension: 0.4,
            fill: true,
            borderWidth: 3,
            pointBackgroundColor: '#00537A',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 7
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { 
          legend: { 
            position: 'top',
            labels: {
              usePointStyle: true,
              padding: 15,
              font: {
                family: 'Poppins',
                size: 12,
                weight: '600'
              }
            }
          },
          tooltip: {
            backgroundColor: 'rgba(0,83,122,0.95)',
            titleFont: {
              family: 'Poppins',
              size: 13,
              weight: '600'
            },
            bodyFont: {
              family: 'Poppins',
              size: 12
            },
            padding: 12,
            cornerRadius: 8,
            displayColors: true
          }
        },
        scales: { 
          y: { 
            beginAtZero: true,
            grid: {
              color: 'rgba(168,232,249,0.2)',
              drawBorder: false
            },
            ticks: {
              font: {
                family: 'Poppins',
                size: 11
              },
              callback: function(value) {
                return '₱' + value.toLocaleString();
              }
            }
          },
          x: {
            grid: {
              display: false
            },
            ticks: {
              font: {
                family: 'Poppins',
                size: 11,
                weight: '500'
              }
            }
          }
        }
      }
    });

    // Schedules Chart (Bar)
    schedulesChart = new Chart(sCtx, {
      type: 'bar',
      data: {
        labels: monthLabels,
        datasets: [{
          label: 'Schedules',
          data: schedulesData,
          backgroundColor: 'rgba(0,83,122,0.8)',
          borderColor: '#00537A',
          borderWidth: 2,
          borderRadius: 8,
          borderSkipped: false
        }]
      },
      options: { 
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            backgroundColor: 'rgba(0,83,122,0.95)',
            titleFont: {
              family: 'Poppins',
              size: 13,
              weight: '600'
            },
            bodyFont: {
              family: 'Poppins',
              size: 12
            },
            padding: 12,
            cornerRadius: 8
          }
        },
        scales: { 
          y: { 
            beginAtZero: true,
            precision: 0,
            grid: {
              color: 'rgba(168,232,249,0.2)',
              drawBorder: false
            },
            ticks: {
              font: {
                family: 'Poppins',
                size: 11
              },
              stepSize: 1
            }
          },
          x: {
            grid: {
              display: false
            },
            ticks: {
              font: {
                family: 'Poppins',
                size: 11,
                weight: '500'
              }
            }
          }
        }
      }
    });

    // ========== WELCOME MODAL TRIGGER ==========
    (function() {
      const shouldShow = <?= $showWelcomeModal ? 'true' : 'false' ?>;
      if (shouldShow) {
        // Wait for Bootstrap to be ready
        document.addEventListener('DOMContentLoaded', () => {
          const modalEl = document.getElementById('welcomeModal');
          if (modalEl) {
            const bsModal = new bootstrap.Modal(modalEl);
            bsModal.show();
            
            // Auto close after 10 seconds
            setTimeout(() => {
              bsModal.hide();
            }, 3000); // 3000 milliseconds = 3 seconds
          }
        });
      }
    })();
  </script>
</body>
</html>
<?php
// Close DB connection at the end of the page
$conn->close();
?>