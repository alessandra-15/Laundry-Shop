<?php
// financials.php — MangTV Financial Dashboard (Dynamic Charts)
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "laundry_db";
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Include notification helpers if file exists
if (file_exists('notification_helpers.php')) {
    include 'notification_helpers.php';
    $unreadNotifCount = getUnreadCount($conn);
} else {
    $unreadNotifCount = 0;
}

// Get filter parameters
$type = $_GET['type'] ?? '';
$filterDate = $_GET['date'] ?? '';
$filterWeek = $_GET['week'] ?? '';
$filterMonth = $_GET['month'] ?? '';
$filterYear = $_GET['year'] ?? '';

// Initialize variables for dynamic content
$chartTitle = "Monthly Income Trend (" . date('Y') . ")";
$chartLabels = [];
$chartData = [];
$periodDescription = "All Time";

// Summary cards (filtered)
$summaryFilter = "";
$summaryParams = [];

if ($type === "day" && !empty($filterDate)) {
    $summaryFilter = "WHERE DATE(transaction_date) = ?";
    $summaryParams[] = $filterDate;
    $periodDescription = date('F d, Y', strtotime($filterDate));
    $chartTitle = "Hourly Revenue - " . $periodDescription;
} elseif ($type === "week" && !empty($filterWeek)) {
    [$year, $weekNum] = explode("-W", $filterWeek);
    $weekStart = date("Y-m-d", strtotime($year . "W" . $weekNum));
    $weekEnd = date("Y-m-d", strtotime($weekStart . " +6 days"));
    $summaryFilter = "WHERE transaction_date BETWEEN ? AND ?";
    $summaryParams[] = $weekStart;
    $summaryParams[] = $weekEnd;
    $periodDescription = date('M d', strtotime($weekStart)) . " - " . date('M d, Y', strtotime($weekEnd));
    $chartTitle = "Daily Revenue - Week " . $weekNum . ", " . $year;
} elseif ($type === "month" && !empty($filterMonth)) {
    $summaryFilter = "WHERE DATE_FORMAT(transaction_date, '%Y-%m') = ?";
    $summaryParams[] = $filterMonth;
    $periodDescription = date('F Y', strtotime($filterMonth . "-01"));
    $chartTitle = "Daily Revenue - " . $periodDescription;
} elseif ($type === "year" && !empty($filterYear)) {
    $summaryFilter = "WHERE YEAR(transaction_date) = ?";
    $summaryParams[] = $filterYear;
    $periodDescription = $filterYear;
    $chartTitle = "Monthly Revenue - " . $filterYear;
}

// Summary cards with filter
$summarySQL = "
  SELECT 
    SUM(payment) AS total_income,
    SUM(CASE WHEN payment_status = 'Paid' THEN payment ELSE 0 END) AS total_paid,
    SUM(CASE WHEN payment_status = 'Pending' THEN payment ELSE 0 END) AS total_pending,
    COUNT(transaction_id) AS total_transactions
  FROM `transaction`
  $summaryFilter
";

if (!empty($summaryParams)) {
    $stmt = $conn->prepare($summarySQL);
    $types = str_repeat("s", count($summaryParams));
    $stmt->bind_param($types, ...$summaryParams);
    $stmt->execute();
    $summary = $stmt->get_result();
} else {
    $summary = $conn->query($summarySQL);
}

$summary_data = $summary->fetch_assoc() ?? ['total_income' => 0, 'total_paid' => 0, 'total_pending' => 0, 'total_transactions' => 0];

// Calculate additional metrics
$avg_transaction = $summary_data['total_transactions'] > 0 
  ? $summary_data['total_income'] / $summary_data['total_transactions'] 
  : 0;

// Dynamic Chart Data based on filter type
if ($type === "day" && !empty($filterDate)) {
    // Hourly breakdown for the selected day
    $chartSQL = "
      SELECT HOUR(transaction_date) AS hour, SUM(payment) AS total
      FROM `transaction`
      WHERE DATE(transaction_date) = ?
      GROUP BY HOUR(transaction_date)
      ORDER BY HOUR(transaction_date)
    ";
    $stmt = $conn->prepare($chartSQL);
    $stmt->bind_param("s", $filterDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $chartLabels[] = date('g A', strtotime($row['hour'] . ':00'));
        $chartData[] = (float)$row['total'];
    }
    
} elseif ($type === "week" && !empty($filterWeek)) {
    // Daily breakdown for the selected week
    [$year, $weekNum] = explode("-W", $filterWeek);
    $weekStart = date("Y-m-d", strtotime($year . "W" . $weekNum));
    $weekEnd = date("Y-m-d", strtotime($weekStart . " +6 days"));
    
    $chartSQL = "
      SELECT DATE(transaction_date) AS day, 
             DAYNAME(transaction_date) AS day_name,
             SUM(payment) AS total
      FROM `transaction`
      WHERE transaction_date BETWEEN ? AND ?
      GROUP BY DATE(transaction_date)
      ORDER BY DATE(transaction_date)
    ";
    $stmt = $conn->prepare($chartSQL);
    $stmt->bind_param("ss", $weekStart, $weekEnd);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $chartLabels[] = $row['day_name'];
        $chartData[] = (float)$row['total'];
    }
    
} elseif ($type === "month" && !empty($filterMonth)) {
    // Daily breakdown for the selected month
    $chartSQL = "
      SELECT DAY(transaction_date) AS day, SUM(payment) AS total
      FROM `transaction`
      WHERE DATE_FORMAT(transaction_date, '%Y-%m') = ?
      GROUP BY DAY(transaction_date)
      ORDER BY DAY(transaction_date)
    ";
    $stmt = $conn->prepare($chartSQL);
    $stmt->bind_param("s", $filterMonth);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $chartLabels[] = 'Day ' . $row['day'];
        $chartData[] = (float)$row['total'];
    }
    
} elseif ($type === "year" && !empty($filterYear)) {
    // Monthly breakdown for the selected year
    $chartSQL = "
      SELECT DATE_FORMAT(transaction_date, '%b') AS month,
             MONTH(transaction_date) AS month_num,
             SUM(payment) AS total
      FROM `transaction`
      WHERE YEAR(transaction_date) = ?
      GROUP BY MONTH(transaction_date)
      ORDER BY MONTH(transaction_date)
    ";
    $stmt = $conn->prepare($chartSQL);
    $stmt->bind_param("s", $filterYear);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $chartLabels[] = $row['month'];
        $chartData[] = (float)$row['total'];
    }
    
} else {
    // Default: Monthly for current year
    $chartSQL = "
      SELECT DATE_FORMAT(transaction_date, '%b') AS month, 
             MONTH(transaction_date) AS month_num,
             SUM(payment) AS total
      FROM `transaction`
      WHERE YEAR(transaction_date) = YEAR(CURDATE())
      GROUP BY MONTH(transaction_date)
      ORDER BY MONTH(transaction_date)
    ";
    $result = $conn->query($chartSQL);
    
    while ($row = $result->fetch_assoc()) {
        $chartLabels[] = $row['month'];
        $chartData[] = (float)$row['total'];
    }
}

// Paid vs Pending Pie Chart (filtered)
$statusSQL = "
  SELECT payment_status, COUNT(*) AS count, SUM(payment) AS total_amount
  FROM `transaction`
  $summaryFilter
  GROUP BY payment_status
";

if (!empty($summaryParams)) {
    $stmt = $conn->prepare($statusSQL);
    $types = str_repeat("s", count($summaryParams));
    $stmt->bind_param($types, ...$summaryParams);
    $stmt->execute();
    $status_query = $stmt->get_result();
} else {
    $status_query = $conn->query($statusSQL);
}

$status_labels = [];
$status_counts = [];
$status_amounts = [];
while ($row = $status_query->fetch_assoc()) {
  $status_labels[] = $row['payment_status'];
  $status_counts[] = (int)$row['count'];
  $status_amounts[] = (float)$row['total_amount'];
}

// Top 5 Customers by Revenue (filtered)
$topCustomersSQL = "
  SELECT customer_id, SUM(payment) AS total_spent, COUNT(*) AS transaction_count
  FROM `transaction`
  $summaryFilter
  GROUP BY customer_id
  ORDER BY total_spent DESC
  LIMIT 5
";

if (!empty($summaryParams)) {
    $stmt = $conn->prepare($topCustomersSQL);
    $types = str_repeat("s", count($summaryParams));
    $stmt->bind_param($types, ...$summaryParams);
    $stmt->execute();
    $top_customers = $stmt->get_result();
} else {
    $top_customers = $conn->query($topCustomersSQL);
}

// Recent Transactions (filtered)
$recentSQL = "
  SELECT transaction_id, customer_id, payment, payment_status, transaction_date
  FROM `transaction`
  $summaryFilter
  ORDER BY transaction_date DESC
  LIMIT 10
";

if (!empty($summaryParams)) {
    $stmt = $conn->prepare($recentSQL);
    $types = str_repeat("s", count($summaryParams));
    $stmt->bind_param($types, ...$summaryParams);
    $stmt->execute();
    $recent = $stmt->get_result();
} else {
    $recent = $conn->query($recentSQL);
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>MangTV Admin - Financials</title>

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
    
    .stat-icon.yellow { 
      background: linear-gradient(135deg, var(--yellow) 0%, #ffe082 100%);
      color: var(--dark-blue);
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
    
    .stat-value.warning {
      color: #ff6b6b;
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
    
    /* Customer List Item */
    .customer-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem;
      border-radius: 12px;
      background: var(--bg-light);
      margin-bottom: 0.75rem;
      transition: all 0.3s;
      border: 1px solid rgba(168,232,249,0.3);
    }
    
    .customer-item:hover {
      background: white;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      transform: translateX(5px);
    }
    
    .customer-rank {
      width: 35px;
      height: 35px;
      background: linear-gradient(135deg, var(--yellow) 0%, #ffe082 100%);
      color: var(--dark-blue);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.9rem;
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
    
    /* Chart Container */
    .chart-container {
      position: relative;
      height: 320px;
    }
    
    /* Filter Section */
    .filter-section {
      background: linear-gradient(135deg, rgba(168,232,249,0.15) 0%, rgba(255,255,255,0.8) 100%);
      padding: 1.25rem;
      border-radius: 16px;
      border: 2px solid rgba(168,232,249,0.4);
      box-shadow: 0 4px 12px rgba(0,83,122,0.08);
      margin-bottom: 1.25rem;
    }
    
    .filter-toggle {
      background: var(--yellow);
      color: var(--dark-blue);
      border: none;
      padding: 0.6rem 1.25rem;
      border-radius: 8px;
      font-weight: 600;
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .filter-toggle:hover { 
      transform: translateY(-2px); 
      box-shadow: 0 4px 12px rgba(255,213,91,0.3); 
    }
    
    .btn-filter { 
      background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%); 
      color: #fff; 
      border: none; 
      border-radius: 8px; 
      font-weight: 600; 
      padding: 0.55rem 1.1rem; 
    }
    
    .btn-filter:hover { 
      transform: translateY(-2px); 
      box-shadow: 0 6px 16px rgba(0,83,122,0.3); 
    }
    
    .btn-clear { 
      background: transparent; 
      color: #dc3545; 
      border: 2px solid #dc3545; 
      border-radius: 8px; 
      font-weight: 600; 
      padding: 0.55rem 1.1rem; 
    }
    
    .btn-clear:hover { 
      background: #dc3545; 
      color: #fff; 
      transform: translateY(-2px); 
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

    .period-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: var(--dark-blue);
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 600;
      margin-bottom: 1rem;
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
    }
    
    @media (max-width:768px) {
      .topbar {
        padding: 1rem;
      }
      
      .stat-card {
        margin-bottom: 1rem;
      }
      
      .chart-container {
        height: 250px;
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
          <a href="financials.php" class="nav-link active">
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
            <h5>Financial Overview</h5>
            <small>Monitor revenue, expenses, and financial health</small>
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
      
      <!-- Filter Bar -->
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="m-0" style="color: var(--dark-blue); font-weight: 700;">Financial Report</h6>
        <button class="filter-toggle" type="button" onclick="toggleStatusFilter()">
          <i class="fa fa-filter"></i> <span id="filterBtnText">Show Filters</span>
        </button>
      </div>

      <div id="statusFilter" class="filter-section" style="display: none;">
        <form method="GET" action="" id="filterForm">
          <div class="row g-3 align-items-end">

            <!-- Filter Type -->
            <div class="col-sm-6 col-md-4 col-lg-3">
              <label class="form-label" style="font-weight:600; color: var(--dark-blue);">Filter Type</label>
              <select name="type" class="form-select" id="filterType" onchange="showDateInputs(this.value)">
                <option value="">Select</option>
                <option value="day" <?= $type === 'day' ? 'selected' : '' ?>>Daily</option>
                <option value="week" <?= $type === 'week' ? 'selected' : '' ?>>Weekly</option>
                <option value="month" <?= $type === 'month' ? 'selected' : '' ?>>Monthly</option>
                <option value="year" <?= $type === 'year' ? 'selected' : '' ?>>Yearly</option>
              </select>
            </div>

            <!-- Daily -->
            <div class="col-sm-6 col-md-4 col-lg-3 date-input" id="dayInput" style="display:none;">
              <label class="form-label" style="font-weight:600; color: var(--dark-blue);">Select Date</label>
              <input type="date" name="date" class="form-control" value="<?= $filterDate ?>">
            </div>

            <!-- Weekly -->
            <div class="col-sm-6 col-md-4 col-lg-3 date-input" id="weekInput" style="display:none;">
              <label class="form-label" style="font-weight:600; color: var(--dark-blue);">Select Week</label>
              <input type="week" name="week" class="form-control" value="<?= $filterWeek ?>">
            </div>

            <!-- Monthly -->
            <div class="col-sm-6 col-md-4 col-lg-3 date-input" id="monthInput" style="display:none;">
              <label class="form-label" style="font-weight:600; color: var(--dark-blue);">Select Month</label>
              <input type="month" name="month" class="form-control" value="<?= $filterMonth ?>">
            </div>

            <!-- Yearly -->
            <div class="col-sm-6 col-md-4 col-lg-3 date-input" id="yearInput" style="display:none;">
              <label class="form-label" style="font-weight:600; color: var(--dark-blue);">Select Year</label>
              <input type="number" name="year" class="form-control" placeholder="2025" value="<?= $filterYear ?>">
            </div>

            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn-filter"><i class="fa fa-search me-1"></i>Apply</button>
              <a href="financials.php" class="btn-clear">Clear</a>
            </div>
          </div>
        </form>
      </div>

      <!-- Period Badge (shows when filter is active) -->
      <?php if (!empty($type)): ?>
      <div class="period-badge">
        <i class="fa fa-calendar-alt"></i>
        <span>Viewing: <?= htmlspecialchars($periodDescription) ?></span>
        <a href="financials.php" style="color: white; margin-left: 0.5rem;" title="Clear filter">
          <i class="fa fa-times-circle"></i>
        </a>
      </div>
      <?php endif; ?>
      
      <!-- Summary Cards -->
      <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
          <div class="stat-card">
            <div class="stat-card-content">
              <div class="stat-icon success">
                <i class="fa fa-coins"></i>
              </div>
              <div class="stat-info">
                <div class="stat-label">Total Income</div>
                <div class="stat-value success">₱<?= number_format($summary_data['total_income'], 2) ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6">
          <div class="stat-card">
            <div class="stat-card-content">
              <div class="stat-icon green">
                <i class="fa fa-check-circle"></i>
              </div>
              <div class="stat-info">
                <div class="stat-label">Paid Transactions</div>
                <div class="stat-value">₱<?= number_format($summary_data['total_paid'], 2) ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6">
          <div class="stat-card">
            <div class="stat-card-content">
              <div class="stat-icon orange">
                <i class="fa fa-clock"></i>
              </div>
              <div class="stat-info">
                <div class="stat-label">Pending Payments</div>
                <div class="stat-value warning">₱<?= number_format($summary_data['total_pending'], 2) ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6">
          <div class="stat-card">
            <div class="stat-card-content">
              <div class="stat-icon blue">
                <i class="fa fa-chart-line"></i>
              </div>
              <div class="stat-info">
                <div class="stat-label">Avg Transaction</div>
                <div class="stat-value">₱<?= number_format($avg_transaction, 2) ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Charts Row -->
      <div class="row g-4 mb-4">
        <div class="col-lg-8">
          <div class="card-custom">
            <div class="card-header-custom">
              <h6><i class="fa fa-chart-bar me-2"></i><?= htmlspecialchars($chartTitle) ?></h6>
            </div>
            <div class="card-body-custom">
              <?php if (count($chartLabels) > 0): ?>
              <div class="chart-container">
                <canvas id="incomeChart"></canvas>
              </div>
              <?php else: ?>
              <div class="text-center py-5 text-muted">
                <i class="fa fa-chart-bar fa-3x mb-3" style="opacity: 0.3;"></i>
                <p class="mb-0">No data available for selected period</p>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="card-custom">
            <div class="card-header-custom">
              <h6><i class="fa fa-pie-chart me-2"></i>Payment Status</h6>
            </div>
            <div class="card-body-custom">
              <?php if (count($status_labels) > 0): ?>
              <div class="chart-container">
                <canvas id="statusChart"></canvas>
              </div>
              
              <!-- Status Legend -->
              <div class="mt-3">
                <?php 
                $total_trans = array_sum($status_counts);
                for ($i = 0; $i < count($status_labels); $i++): 
                  $percent = $total_trans > 0 ? ($status_counts[$i] / $total_trans) * 100 : 0;
                  $color = $status_labels[$i] === 'Paid' ? '#28a745' : '#ffc107';
                ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div class="d-flex align-items-center gap-2">
                    <div style="width: 12px; height: 12px; background: <?= $color ?>; border-radius: 3px;"></div>
                    <span class="small"><?= htmlspecialchars($status_labels[$i]) ?></span>
                  </div>
                  <span class="small fw-bold"><?= number_format($percent, 1) ?>%</span>
                </div>
                <?php endfor; ?>
              </div>
              <?php else: ?>
              <div class="text-center py-5 text-muted">
                <i class="fa fa-pie-chart fa-3x mb-3" style="opacity: 0.3;"></i>
                <p class="mb-0">No payment data available</p>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Content Row -->
      <div class="row g-4 mb-4">
        <!-- Top Customers -->
        <div class="col-lg-4">
          <div class="card-custom">
            <div class="card-header-custom">
              <h6><i class="fa fa-trophy me-2"></i>Top 5 Customers</h6>
            </div>
            <div class="card-body-custom">
              <?php if ($top_customers && $top_customers->num_rows > 0): ?>
                <?php $rank = 1; while ($customer = $top_customers->fetch_assoc()): ?>
                  <div class="customer-item">
                    <div class="d-flex align-items-center gap-3">
                      <div class="customer-rank">
                        <?php if ($rank === 1): ?>
                          <i class="fa fa-crown"></i>
                        <?php else: ?>
                          <?= $rank ?>
                        <?php endif; ?>
                      </div>
                      <div>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($customer['customer_id']) ?></div>
                        <small class="text-muted">
                          <i class="fa fa-shopping-bag me-1"></i><?= $customer['transaction_count'] ?> transactions
                        </small>
                      </div>
                    </div>
                    <div class="text-end">
                      <div class="fw-bold text-success">₱<?= number_format($customer['total_spent'], 2) ?></div>
                    </div>
                  </div>
                <?php $rank++; endwhile; ?>
              <?php else: ?>
                <div class="text-muted text-center py-3">No customer data available</div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Recent Transactions -->
        <div class="col-lg-8">
          <div class="card-custom">
            <div class="card-header-custom">
              <div class="d-flex justify-content-between align-items-center">
                <h6><i class="fa fa-receipt me-2"></i>Recent Transactions</h6>
                <a href="payments.php" class="btn btn-sm" style="background: var(--dark-blue); color: white; border-radius: 8px; padding: 0.4rem 1rem;">
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
                    <?php if ($recent && $recent->num_rows > 0): ?>
                      <?php while ($row = $recent->fetch_assoc()): ?>
                        <tr>
                          <td><strong class="text-primary">#<?= htmlspecialchars($row['transaction_id']) ?></strong></td>
                          <td><?= htmlspecialchars($row['customer_id']) ?></td>
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
                        <td colspan="5" class="text-center py-4">
                          <div class="text-muted">
                            <i class="fa fa-inbox fa-2x mb-2 d-block" style="opacity: 0.3;"></i>
                            <small>No transactions found for selected period</small>
                          </div>
                        </td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Financial Insights -->
      <div class="row g-4 mb-4">
        <div class="col-lg-12">
          <div class="card-custom">
            <div class="card-header-custom">
              <h6><i class="fa fa-lightbulb me-2"></i>Financial Insights</h6>
            </div>
            <div class="card-body-custom">
              <div class="row g-3">
                <div class="col-md-3">
                  <div class="p-3 text-center" style="background: linear-gradient(135deg, rgba(40,167,69,0.1) 0%, rgba(32,201,151,0.1) 100%); border-radius: 12px;">
                    <div class="stat-icon success mx-auto mb-2" style="width: 50px; height: 50px;">
                      <i class="fa fa-percentage" style="font-size: 1.2rem;"></i>
                    </div>
                    <small class="text-muted d-block">Payment Success Rate</small>
                    <h5 class="mb-0 mt-1">
                      <?php 
                        $success_rate = $summary_data['total_transactions'] > 0 
                          ? ($summary_data['total_paid'] / $summary_data['total_income']) * 100 
                          : 0;
                        echo number_format($success_rate, 1);
                      ?>%
                    </h5>
                  </div>
                </div>

                <div class="col-md-3">
                  <div class="p-3 text-center" style="background: linear-gradient(135deg, rgba(0,83,122,0.1) 0%, rgba(0,107,153,0.1) 100%); border-radius: 12px;">
                    <div class="stat-icon blue mx-auto mb-2" style="width: 50px; height: 50px;">
                      <i class="fa fa-receipt" style="font-size: 1.2rem;"></i>
                    </div>
                    <small class="text-muted d-block">Total Transactions</small>
                    <h5 class="mb-0 mt-1"><?= number_format($summary_data['total_transactions']) ?></h5>
                  </div>
                </div>

                <div class="col-md-3">
                  <div class="p-3 text-center" style="background: linear-gradient(135deg, rgba(255,213,91,0.2) 0%, rgba(255,224,130,0.2) 100%); border-radius: 12px;">
                    <div class="stat-icon yellow mx-auto mb-2" style="width: 50px; height: 50px;">
                      <i class="fa fa-calculator" style="font-size: 1.2rem;"></i>
                    </div>
                    <small class="text-muted d-block">Average per Period</small>
                    <h5 class="mb-0 mt-1">
                      ₱<?php 
                        $period_avg = count($chartData) > 0 ? array_sum($chartData) / count($chartData) : 0;
                        echo number_format($period_avg, 2);
                      ?>
                    </h5>
                  </div>
                </div>

                <div class="col-md-3">
                  <div class="p-3 text-center" style="background: linear-gradient(135deg, rgba(255,107,107,0.1) 0%, rgba(255,165,0,0.1) 100%); border-radius: 12px;">
                    <div class="stat-icon orange mx-auto mb-2" style="width: 50px; height: 50px;">
                      <i class="fa fa-hourglass-half" style="font-size: 1.2rem;"></i>
                    </div>
                    <small class="text-muted d-block">Pending Amount</small>
                    <h5 class="mb-0 mt-1">₱<?= number_format($summary_data['total_pending'], 2) ?></h5>
                  </div>
                </div>
              </div>

              <!-- Quick Actions -->
              <div class="mt-4 pt-3 border-top">
                <div class="d-flex flex-wrap gap-2">
                  <button class="btn btn-sm" style="background: var(--dark-blue); color: white; border-radius: 8px; padding: 0.5rem 1rem;" onclick="window.print();">
                    <i class="fa fa-print me-2"></i>Print Report
                  </button>
                  <button class="btn btn-sm" style="background: var(--yellow); color: var(--dark-blue); border-radius: 8px; padding: 0.5rem 1rem;">
                    <i class="fa fa-file-excel me-2"></i>Export to Excel
                  </button>
                  <button class="btn btn-sm" style="background: var(--light-blue); color: var(--dark-blue); border-radius: 8px; padding: 0.5rem 1rem;">
                    <i class="fa fa-file-pdf me-2"></i>Generate PDF
                  </button>
                  <a href="payments.php" class="btn btn-sm" style="background: #6c757d; color: white; border-radius: 8px; padding: 0.5rem 1rem;">
                    <i class="fa fa-eye me-2"></i>View All Payments
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

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

    // Filter toggle
    function toggleStatusFilter() {
      const box = document.getElementById("statusFilter");
      const text = document.getElementById("filterBtnText");

      box.style.display = box.style.display === "none" ? "block" : "none";
      text.textContent = box.style.display === "none" ? "Show Filters" : "Hide Filters";
    }

    function showDateInputs(type) {
      // Hide all first
      document.querySelectorAll(".date-input").forEach(div => div.style.display = "none");

      if (type === "day")  document.getElementById("dayInput").style.display = "block";
      if (type === "week") document.getElementById("weekInput").style.display = "block";
      if (type === "month") document.getElementById("monthInput").style.display = "block";
      if (type === "year") document.getElementById("yearInput").style.display = "block";
    }

    // Auto-show based on GET parameter
    showDateInputs("<?= $type ?>");

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


    // Chart data from PHP
    const chartLabels = <?= json_encode($chartLabels) ?>;
    const chartData = <?= json_encode($chartData) ?>;
    const statusLabels = <?= json_encode($status_labels) ?>;
    const statusCounts = <?= json_encode($status_counts) ?>;

    // Chart.js Common Options
    const commonOptions = {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
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
      }
    };

    // Dynamic Income Chart
    <?php if (count($chartLabels) > 0): ?>
    const ctxIncome = document.getElementById('incomeChart').getContext('2d');
    const incomeChart = new Chart(ctxIncome, {
      type: 'bar',
      data: {
        labels: chartLabels,
        datasets: [
          {
            label: 'Revenue (₱)',
            data: chartData,
            backgroundColor: 'rgba(255,213,91,0.8)',
            borderColor: '#FFD35B',
            borderWidth: 2,
            borderRadius: 8,
            borderSkipped: false
          },
          {
            label: 'Trend',
            type: 'line',
            data: chartData,
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
        ...commonOptions,
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
    <?php endif; ?>

    // Payment Status Pie Chart
    <?php if (count($status_labels) > 0): ?>
    const ctxStatus = document.getElementById('statusChart').getContext('2d');
    const statusChart = new Chart(ctxStatus, {
      type: 'doughnut',
      data: {
        labels: statusLabels,
        datasets: [{
          data: statusCounts,
          backgroundColor: [
            '#28a745',
            '#ffc107'
          ],
          borderWidth: 3,
          borderColor: '#fff'
        }]
      },
      options: {
        ...commonOptions,
        cutout: '65%',
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            ...commonOptions.plugins.tooltip,
            callbacks: {
              label: function(context) {
                const label = context.label || '';
                const value = context.parsed || 0;
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = ((value / total) * 100).toFixed(1);
                return label + ': ' + value + ' (' + percentage + '%)';
              }
            }
          }
        }
      }
    });
    <?php endif; ?>
  </script>
</body>
</html>
<?php $conn->close(); ?>