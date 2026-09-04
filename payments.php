<?php
// payments.php — MangTV Payments Management (Redesigned to match dashboard)
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "laundry_db";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
  die("Database connection failed: " . $conn->connect_error);
}

// Include notification helpers if file exists
if (file_exists('notification_helpers.php')) {
    include 'notification_helpers.php';
    $unreadNotifCount = getUnreadCount($conn);
} else {
    $unreadNotifCount = 0; // Default if notification system not set up yet
}

$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_customer = isset($_GET['customer_id']) ? $_GET['customer_id'] : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

$query = "
  SELECT 
    transaction_id,
    customer_id,
    schedule_id,
    laundry_weight,
    cost_per_weight,
    add_ons_cost,
    pick_deliver_cost,
    payment,
    payment_status,
    transaction_date
  FROM `transaction`
  WHERE 1
";

if (!empty($filter_status)) {
  $query .= " AND payment_status = '" . $conn->real_escape_string($filter_status) . "'";
}
if (!empty($filter_customer)) {
  $query .= " AND customer_id = '" . $conn->real_escape_string($filter_customer) . "'";
}
if (!empty($filter_date)) {
  $query .= " AND DATE(transaction_date) = '" . $conn->real_escape_string($filter_date) . "'";
}
$query .= " ORDER BY transaction_date DESC, transaction_id DESC";
$result = $conn->query($query);

$summary = $conn->query("
  SELECT 
    COUNT(transaction_id) AS total_transactions,
    SUM(payment) AS total_payments,
    SUM(laundry_weight) AS total_weight,
    SUM(CASE WHEN payment_status = 'Paid' THEN 1 ELSE 0 END) AS paid_count,
    SUM(CASE WHEN payment_status = 'Pending' THEN 1 ELSE 0 END) AS pending_count
  FROM `transaction`
");
$summary_data = $summary->fetch_assoc();

// Get average payment
$avg_payment = $summary_data['total_transactions'] > 0 
  ? $summary_data['total_payments'] / $summary_data['total_transactions'] 
  : 0;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>MangTV Admin - Payments</title>

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
    }
    
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 24px rgba(0,0,0,0.1);
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
    
    .stat-icon.orange { 
      background: linear-gradient(135deg, #ff6b6b 0%, #ffa500 100%);
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
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--dark-blue);
      line-height: 1;
      word-break: break-word;
    }
    
    .stat-value.success {
      color: #198754;
    }
    
    @media (max-width: 1400px) {
      .stat-value {
        font-size: 1.3rem;
      }
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
    
    /* Filter Section */
    .filter-section {
      background: white;
      border-radius: 16px;
      padding: 1.5rem;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
      border: 1px solid rgba(168,232,249,0.2);
      margin-bottom: 1.5rem;
    }
    
    .filter-section h6 {
      color: var(--dark-blue);
      font-weight: 700;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .form-control, .form-select {
      border-radius: 10px;
      border: 1px solid rgba(168,232,249,0.5);
      padding: 0.6rem 1rem;
      font-size: 0.9rem;
      transition: all 0.3s;
    }
    
    .form-control:focus, .form-select:focus {
      border-color: var(--yellow);
      box-shadow: 0 0 0 0.25rem rgba(255,213,91,0.25);
    }
    
    .btn-primary {
      background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
      border: none;
      padding: 0.6rem 1.5rem;
      border-radius: 10px;
      font-weight: 600;
      transition: all 0.3s;
    }
    
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(0,83,122,0.3);
    }
    
    .btn-secondary {
      background: #6c757d;
      border: none;
      padding: 0.6rem 1.5rem;
      border-radius: 10px;
      font-weight: 600;
      transition: all 0.3s;
    }
    
    .btn-secondary:hover {
      background: #5a6268;
      transform: translateY(-2px);
    }
    
    .btn-export {
      background: var(--yellow);
      color: var(--dark-blue);
      border: none;
      padding: 0.6rem 1.5rem;
      border-radius: 10px;
      font-weight: 600;
      transition: all 0.3s;
    }
    
    .btn-export:hover {
      background: #ffc107;
      color: var(--dark-blue);
      transform: translateY(-2px);
    }
    
    /* Tables */
    .table-custom {
      margin: 0;
    }
    
    .table-custom thead th { 
      background: var(--yellow);
      color: var(--dark-blue);
      font-weight: 600;
      font-size: 0.85rem;
      padding: 1rem 0.75rem;
      border: none;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      text-align: center;
      vertical-align: middle;
    }
    
    .table-custom tbody tr {
      transition: all 0.3s;
    }
    
    .table-custom tbody tr:hover {
      background: rgba(168,232,249,0.1);
    }
    
    .table-custom tbody td {
      padding: 1rem 0.75rem;
      vertical-align: middle;
      border-bottom: 1px solid rgba(168,232,249,0.2);
      font-size: 0.9rem;
      text-align: center;
    }
    
    /* Badges */
    .badge-custom {
      padding: 0.4rem 0.75rem;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.75rem;
      letter-spacing: 0.5px;
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
      
      .filter-section .row {
        row-gap: 1rem;
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
          <a href="dashboard.php" class="nav-link">
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
                    <a href="walkin.php" class="nav-link ">
                        <i class="fa fa-person-walking"></i>
                        <span class="nav-text">Walk-in</span>
                    </a>
                </li>
        <li class="nav-item">
          <a href="payments.php" class="nav-link active">
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
            <h5>Payments Management</h5>
            <small>View and manage all transaction payments</small>
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
          <div class="stat-card">
            <div class="stat-card-content">
              <div class="stat-icon blue">
                <i class="fa fa-receipt"></i>
              </div>
              <div class="stat-info">
                <div class="stat-label">Total Transactions</div>
                <div class="stat-value"><?= number_format($summary_data['total_transactions'] ?? 0) ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6">
          <div class="stat-card">
            <div class="stat-card-content">
              <div class="stat-icon success">
                <i class="fa fa-coins"></i>
              </div>
              <div class="stat-info">
                <div class="stat-label">Total Payments</div>
                <div class="stat-value success">₱<?= number_format($summary_data['total_payments'] ?? 0, 2) ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6">
          <div class="stat-card">
            <div class="stat-card-content">
              <div class="stat-icon green">
                <i class="fa fa-weight-hanging"></i>
              </div>
              <div class="stat-info">
                <div class="stat-label">Total Weight</div>
                <div class="stat-value"><?= number_format($summary_data['total_weight'] ?? 0, 2) ?> kg</div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6">
          <div class="stat-card">
            <div class="stat-card-content">
              <div class="stat-icon orange">
                <i class="fa fa-chart-line"></i>
              </div>
              <div class="stat-info">
                <div class="stat-label">Average Payment</div>
                <div class="stat-value">₱<?= number_format($avg_payment, 2) ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Stats Row -->
      <div class="row g-4 mb-4">
        <div class="col-md-6">
          <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="stat-label">Paid Transactions</div>
                <div class="stat-value text-success"><?= number_format($summary_data['paid_count'] ?? 0) ?></div>
              </div>
              <div class="stat-icon success" style="width: 50px; height: 50px;">
                <i class="fa fa-check-circle" style="font-size: 1.3rem;"></i>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="stat-label">Pending Transactions</div>
                <div class="stat-value text-warning"><?= number_format($summary_data['pending_count'] ?? 0) ?></div>
              </div>
              <div class="stat-icon orange" style="width: 50px; height: 50px;">
                <i class="fa fa-clock" style="font-size: 1.3rem;"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Filter Section -->
      <div class="filter-section">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6><i class="fa fa-filter me-2"></i>Filter Transactions</h6>
          <button class="btn btn-export" onclick="window.print();">
            <i class="fa fa-file-export me-2"></i>Export Report
          </button>
        </div>
        <form method="get">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label small fw-semibold text-muted">Payment Status</label>
              <select name="status" class="form-select">
                <option value="">All Status</option>
                <option value="Paid" <?= $filter_status == 'Paid' ? 'selected' : '' ?>>Paid</option>
                <option value="Pending" <?= $filter_status == 'Pending' ? 'selected' : '' ?>>Pending</option>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-semibold text-muted">Customer</label>
              <select name="customer_id" class="form-select">
                <option value="">All Customers</option>
                <?php 
                $customers = $conn->query("SELECT DISTINCT customer_id FROM `transaction` ORDER BY customer_id ASC");
                while ($cust = $customers->fetch_assoc()): ?>
                  <option value="<?= $cust['customer_id'] ?>" <?= $filter_customer == $cust['customer_id'] ? 'selected' : '' ?>>
                    <?= $cust['customer_id'] ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-semibold text-muted">Transaction Date</label>
              <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>">
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-semibold text-muted d-block">&nbsp;</label>
              <button type="submit" class="btn btn-primary w-100">
                <i class="fa fa-search me-2"></i>Apply Filters
              </button>
            </div>
          </div>
          
          <div class="row mt-3">
            <div class="col-md-12 text-end">
              <a href="payments.php" class="btn btn-secondary">
                <i class="fa fa-redo me-2"></i>Reset Filters
              </a>
            </div>
          </div>
        </form>
      </div>

      <!-- Payments Table -->
      <div class="card-custom">
        <div class="card-header-custom">
          <div class="d-flex justify-content-between align-items-center">
            <h6><i class="fa fa-table me-2"></i>Transaction Records</h6>
            <span class="badge bg-primary badge-custom">
              <?= $result ? $result->num_rows : 0 ?> Records
            </span>
          </div>
        </div>
        <div class="card-body-custom p-0">
          <div class="table-responsive">
            <table class="table table-custom">
              <thead>
                <tr>
                  <th>Transaction ID</th>
                  <th>Customer ID</th>
                  <th>Schedule ID</th>
                  <th>Weight (kg)</th>
                  <th>Cost/kg</th>
                  <th>Add-ons</th>
                  <th>Pick/Deliver</th>
                  <th>Total Payment</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                  <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                      <td><strong class="text-primary">#<?= htmlspecialchars($row['transaction_id']) ?></strong></td>
                      <td><?= htmlspecialchars($row['customer_id']) ?></td>
                      <td><span class="badge bg-info badge-custom">#<?= htmlspecialchars($row['schedule_id']) ?></span></td>
                      <td><strong><?= htmlspecialchars($row['laundry_weight']) ?> kg</strong></td>
                      <td>₱<?= number_format($row['cost_per_weight'], 2) ?></td>
                      <td>₱<?= number_format($row['add_ons_cost'], 2) ?></td>
                      <td>₱<?= number_format($row['pick_deliver_cost'], 2) ?></td>
                      <td><strong class="text-success">₱<?= number_format($row['payment'], 2) ?></strong></td>
                      <td>
                        <?php if (strtolower($row['payment_status']) === 'paid'): ?>
                          <span class="badge bg-success badge-custom">
                            <i class="fa fa-check-circle me-1"></i>Paid
                          </span>
                        <?php else: ?>
                          <span class="badge bg-warning text-dark badge-custom">
                            <i class="fa fa-clock me-1"></i><?= htmlspecialchars($row['payment_status']) ?>
                          </span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <small>
                          <i class="fa fa-calendar me-1"></i>
                          <?= date('M d, Y', strtotime($row['transaction_date'])) ?>
                        </small>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="10" class="text-center py-5">
                      <div class="text-muted">
                        <i class="fa fa-inbox fa-3x mb-3 d-block" style="opacity: 0.3;"></i>
                        <h6>No transactions found</h6>
                        <p class="mb-0 small">Try adjusting your filters or check back later.</p>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Payment Breakdown -->
      <?php if ($result && $result->num_rows > 0): ?>
      <div class="row g-4 mt-4">
        <div class="col-lg-4">
          <div class="card-custom">
            <div class="card-header-custom">
              <h6><i class="fa fa-chart-pie me-2"></i>Payment Breakdown</h6>
            </div>
            <div class="card-body-custom">
              <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                <div>
                  <small class="text-muted d-block">Total Revenue</small>
                  <h4 class="mb-0 text-success">₱<?= number_format($summary_data['total_payments'] ?? 0, 2) ?></h4>
                </div>
                <div class="stat-icon success" style="width: 45px; height: 45px;">
                  <i class="fa fa-money-bill-wave" style="font-size: 1.2rem;"></i>
                </div>
              </div>
              
              <div class="mb-3">
                <div class="d-flex justify-content-between mb-2">
                  <span class="small">Paid</span>
                  <span class="small fw-bold text-success"><?= $summary_data['paid_count'] ?? 0 ?> transactions</span>
                </div>
                <div class="progress" style="height: 8px; border-radius: 10px;">
                  <?php 
                    $paid_percent = $summary_data['total_transactions'] > 0 
                      ? ($summary_data['paid_count'] / $summary_data['total_transactions']) * 100 
                      : 0;
                  ?>
                  <div class="progress-bar bg-success" style="width: <?= $paid_percent ?>%; border-radius: 10px;"></div>
                </div>
              </div>
              
              <div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="small">Pending</span>
                  <span class="small fw-bold text-warning"><?= $summary_data['pending_count'] ?? 0 ?> transactions</span>
                </div>
                <div class="progress" style="height: 8px; border-radius: 10px;">
                  <?php 
                    $pending_percent = $summary_data['total_transactions'] > 0 
                      ? ($summary_data['pending_count'] / $summary_data['total_transactions']) * 100 
                      : 0;
                  ?>
                  <div class="progress-bar bg-warning" style="width: <?= $pending_percent ?>%; border-radius: 10px;"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-8">
          <div class="card-custom">
            <div class="card-header-custom">
              <h6><i class="fa fa-info-circle me-2"></i>Quick Statistics</h6>
            </div>
            <div class="card-body-custom">
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="p-3 border rounded" style="border-radius: 12px !important; border-color: rgba(168,232,249,0.3) !important;">
                    <div class="d-flex align-items-center gap-3">
                      <div class="stat-icon blue" style="width: 45px; height: 45px;">
                        <i class="fa fa-peso-sign" style="font-size: 1.2rem;"></i>
                      </div>
                      <div>
                        <small class="text-muted d-block">Average Payment</small>
                        <h5 class="mb-0">₱<?= number_format($avg_payment, 2) ?></h5>
                      </div>
                    </div>
                  </div>
                </div>
                
                <div class="col-md-6">
                  <div class="p-3 border rounded" style="border-radius: 12px !important; border-color: rgba(168,232,249,0.3) !important;">
                    <div class="d-flex align-items-center gap-3">
                      <div class="stat-icon green" style="width: 45px; height: 45px;">
                        <i class="fa fa-weight-hanging" style="font-size: 1.2rem;"></i>
                      </div>
                      <div>
                        <small class="text-muted d-block">Total Weight Processed</small>
                        <h5 class="mb-0"><?= number_format($summary_data['total_weight'] ?? 0, 2) ?> kg</h5>
                      </div>
                    </div>
                  </div>
                </div>
                
                <div class="col-md-6">
                  <div class="p-3 border rounded" style="border-radius: 12px !important; border-color: rgba(168,232,249,0.3) !important;">
                    <div class="d-flex align-items-center gap-3">
                      <div class="stat-icon success" style="width: 45px; height: 45px;">
                        <i class="fa fa-check-double" style="font-size: 1.2rem;"></i>
                      </div>
                      <div>
                        <small class="text-muted d-block">Completion Rate</small>
                        <h5 class="mb-0"><?= number_format($paid_percent, 1) ?>%</h5>
                      </div>
                    </div>
                  </div>
                </div>
                
                <div class="col-md-6">
                  <div class="p-3 border rounded" style="border-radius: 12px !important; border-color: rgba(168,232,249,0.3) !important;">
                    <div class="d-flex align-items-center gap-3">
                      <div class="stat-icon orange" style="width: 45px; height: 45px;">
                        <i class="fa fa-hourglass-half" style="font-size: 1.2rem;"></i>
                      </div>
                      <div>
                        <small class="text-muted d-block">Pending Rate</small>
                        <h5 class="mb-0"><?= number_format($pending_percent, 1) ?>%</h5>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <footer>
        <p class="mb-0">© <?= date('Y') ?> <strong>MangTV Laundry Shop</strong> - All Rights Reserved</p>
      </footer>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Sidebar toggle functionality
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


  </script>
</body>
</html>
<?php $conn->close(); ?>