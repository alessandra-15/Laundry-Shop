<?php
// digital_record.php — MangTV Digital Records with Advanced Filters
include 'db_connect.php';

// Include notification helpers if file exists
if (file_exists('notification_helpers.php')) {
    include 'notification_helpers.php';
    $unreadNotifCount = getUnreadCount($conn);
} else {
    $unreadNotifCount = 0; // Default if notification system not set up yet
}

// Get filter parameters
$filterDate = $_GET['filter_date'] ?? '';
$filterMonth = $_GET['filter_month'] ?? '';
$filterPaymentStatus = $_GET['filter_payment'] ?? '';
$filterLaundryStatus = $_GET['filter_laundry'] ?? '';

// Build WHERE clause based on filters
$whereConditions = [];
if (!empty($filterDate)) {
    $whereConditions[] = "s.date = '" . $conn->real_escape_string($filterDate) . "'";
}
if (!empty($filterMonth)) {
    $whereConditions[] = "DATE_FORMAT(s.date, '%Y-%m') = '" . $conn->real_escape_string($filterMonth) . "'";
}
if (!empty($filterPaymentStatus)) {
    $whereConditions[] = "t.payment_status = '" . $conn->real_escape_string($filterPaymentStatus) . "'";
}
if (!empty($filterLaundryStatus)) {
    if ($filterLaundryStatus === 'Waiting') {
        $whereConditions[] = "(tr.laundry_status IS NULL OR tr.laundry_status = 'Waiting')";
    } else {
        $whereConditions[] = "tr.laundry_status = '" . $conn->real_escape_string($filterLaundryStatus) . "'";
    }
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Fetch digital records
$records = [];
$query = "SELECT t.Transaction_ID, CONCAT(c.first_name,' ',c.last_name) AS customer_name,
                 s.date, s.time, s.service, s.add_ons, s.pick_deliver,
                 t.laundry_weight, t.payment, t.payment_status,
                 tr.laundry_status
          FROM `transaction` t
          JOIN customer_info c ON t.Customer_ID=c.Customer_ID
          JOIN schedule s ON t.Schedule_ID=s.Schedule_ID
          LEFT JOIN tracking tr ON t.Schedule_ID=tr.Schedule_ID
          $whereClause
          ORDER BY t.transaction_date DESC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
}

// Get statistics
$totalRecords = count($records);
$totalWeight = array_sum(array_column($records, 'laundry_weight'));
$totalRevenue = array_sum(array_column($records, 'payment'));
$pendingPayments = count(array_filter($records, function($r) { 
    return strtolower($r['payment_status']) !== 'paid'; 
}));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>MangTV - Digital Records</title>

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
    
    .stat-icon.purple { 
      background: linear-gradient(135deg, #6f42c1 0%, #8b5cf6 100%);
    }
    
    .stat-icon.success { 
      background: linear-gradient(135deg, #198754 0%, #20c997 100%);
    }
    
    .stat-icon.warning { 
      background: linear-gradient(135deg, #fd7e14 0%, #ffc107 100%);
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
      background: linear-gradient(135deg, rgba(168,232,249,0.15) 0%, rgba(255,255,255,0.8) 100%);
      padding: 1.5rem;
      border-radius: 16px;
      margin-bottom: 1.5rem;
      border: 2px solid rgba(168,232,249,0.4);
      box-shadow: 0 4px 12px rgba(0,83,122,0.08);
    }
    
    .filter-section label {
      font-weight: 600;
      color: var(--dark-blue);
      font-size: 0.9rem;
      margin-bottom: 0.6rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .filter-section label i {
      color: var(--yellow);
      font-size: 1rem;
    }
    
    .filter-input-wrapper {
      position: relative;
    }
    
    .filter-input-wrapper::before {
      content: '';
      position: absolute;
      left: 0;
      bottom: 0;
      width: 0;
      height: 3px;
      background: linear-gradient(90deg, var(--yellow) 0%, var(--light-blue) 100%);
      transition: width 0.3s ease;
      border-radius: 3px;
    }
    
    .filter-input-wrapper:focus-within::before {
      width: 100%;
    }
    
    .filter-section .form-control,
    .filter-section .form-select {
      border-radius: 12px;
      border: 2px solid rgba(168,232,249,0.6);
      padding: 0.75rem 1.25rem;
      padding-left: 3rem;
      font-size: 0.9rem;
      font-weight: 500;
      color: var(--dark-blue);
      background: white;
      transition: all 0.3s ease;
      box-shadow: 0 2px 6px rgba(0,83,122,0.05);
    }
    
    .filter-section .form-control:hover,
    .filter-section .form-select:hover {
      border-color: var(--light-blue);
      box-shadow: 0 4px 12px rgba(168,232,249,0.3);
      transform: translateY(-2px);
    }
    
    .filter-section .form-control:focus,
    .filter-section .form-select:focus {
      border-color: var(--yellow);
      box-shadow: 0 0 0 0.3rem rgba(255,213,91,0.25), 0 4px 16px rgba(255,213,91,0.2);
      outline: none;
      transform: translateY(-2px);
    }
    
    /* Custom Date Input Styling */
    .filter-section input[type="date"],
    .filter-section input[type="month"] {
      position: relative;
      cursor: pointer;
    }
    
    .filter-section input[type="date"]::-webkit-calendar-picker-indicator,
    .filter-section input[type="month"]::-webkit-calendar-picker-indicator {
      position: absolute;
      right: 1rem;
      cursor: pointer;
      color: var(--dark-blue);
      font-size: 1.2rem;
      filter: invert(27%) sepia(51%) saturate(2878%) hue-rotate(169deg) brightness(104%) contrast(97%);
    }
    
    .filter-section input[type="date"]::-webkit-calendar-picker-indicator:hover,
    .filter-section input[type="month"]::-webkit-calendar-picker-indicator:hover {
      filter: invert(77%) sepia(82%) saturate(1352%) hue-rotate(342deg) brightness(103%) contrast(101%);
    }
    
    /* Custom Select Arrow */
    .filter-section .form-select {
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%2300537A' d='M8 11L3 6h10z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 1rem center;
      background-size: 16px 12px;
      padding-right: 3rem;
      cursor: pointer;
    }
    
    .filter-section .form-select:hover {
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%23FFD35B' d='M8 11L3 6h10z'/%3E%3C/svg%3E");
    }
    
    /* Input Icons */
    .filter-input-icon {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--dark-blue);
      font-size: 1.1rem;
      pointer-events: none;
      transition: all 0.3s;
    }
    
    .filter-input-wrapper:focus-within .filter-input-icon {
      color: var(--yellow);
      transform: translateY(-50%) scale(1.1);
    }
    
    .btn-filter {
      background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
      color: white;
      border: none;
      padding: 0.6rem 1.5rem;
      border-radius: 8px;
      font-weight: 600;
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .btn-filter:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(0,83,122,0.3);
      color: white;
    }
    
    .btn-clear {
      background: transparent;
      color: #dc3545;
      border: 2px solid #dc3545;
      padding: 0.6rem 1.5rem;
      border-radius: 8px;
      font-weight: 600;
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .btn-clear:hover {
      background: #dc3545;
      color: white;
      transform: translateY(-2px);
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
      margin-bottom: 1rem;
    }
    
    .filter-toggle:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(255,213,91,0.3);
    }
    
    .active-filters {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      margin-top: 1rem;
    }
    
    .filter-badge {
      background: var(--light-blue);
      color: var(--dark-blue);
      padding: 0.4rem 0.75rem;
      border-radius: 8px;
      font-size: 0.8rem;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .filter-badge i {
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .filter-badge i:hover {
      transform: scale(1.2);
      color: #dc3545;
    }
    
    /* Search Bar */
    .search-bar {
      position: relative;
      width: 100%;
    }
    
    .search-bar input {
      padding: 0.75rem 1rem 0.75rem 3rem;
      border-radius: 12px;
      border: 2px solid rgba(168,232,249,0.5);
      background: white;
      font-size: 0.95rem;
      transition: all 0.3s;
      width: 100%;
    }
    
    .search-bar input:focus {
      outline: none;
      border-color: var(--yellow);
      box-shadow: 0 4px 12px rgba(255,213,91,0.2);
    }
    
    .search-bar i {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: #6c757d;
      font-size: 1.1rem;
    }
    
    /* Table Styles */
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
      white-space: nowrap;
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
      font-size: 0.85rem;
    }
    
    /* Badges */
    .badge-custom { padding:.4rem .75rem; border-radius:8px; font-weight:600; font-size:.75rem; letter-spacing:.5px; white-space:nowrap; }
    .badge-paid { background:#d4edda; color:#155724; }
    .badge-unpaid { background:#fff3cd; color:#856404; }
    .badge-processing { background:#cfe2ff; color:#084298; }
    .badge-completed { background:#d1e7dd; color:#0f5132; }
    .badge-waiting { background:#e2e3e5; color:#41464b; }
    .badge-scheduled { background:#e7f5ff; color:#0b67ce; }
    .badge-pickedup  { background:#fff4e6; color:#b45309; }
    .badge-ready     { background:#e6ffed; color:#0f5132; }
    
    /* Action Buttons */
    .action-btns {
      display: flex;
      gap: 0.25rem;
      justify-content: center;
    }
    
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
      font-size: 0.85rem;
    }
    
    .btn-action:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .btn-action.btn-info {
      background: var(--light-blue);
      color: var(--dark-blue);
    }
    
    .btn-action.btn-warning {
      background: var(--yellow);
      color: var(--dark-blue);
    }
    
    .btn-action.btn-danger {
      background: #dc3545;
      color: white;
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
    
    /* Themed Delete Confirmation Modal */
.modal-confirm .modal-header {
  background: linear-gradient(135deg, var(--light-blue) 0%, rgba(168,232,249,0.3) 100%);
  border-bottom: 1px solid rgba(168,232,249,0.3);
  color: var(--dark-blue);
}
.modal-confirm .modal-title {
  font-weight: 700;
  color: var(--dark-blue);
}
.modal-confirm .modal-title i {
  color: var(--yellow);
}
.modal-confirm .modal-body {
  color: var(--text-dark);
}
.modal-confirm .btn-cancel {
  background: var(--bg-light);
  color: var(--dark-blue);
  border: 1px solid rgba(168,232,249,0.6);
  border-radius: 10px;
  font-weight: 600;
}
.modal-confirm .btn-cancel:hover {
  background: var(--light-blue);
  border-color: var(--light-blue);
}
.modal-confirm .btn-delete {
  background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-weight: 700;
  box-shadow: 0 6px 16px rgba(0,83,122,0.2);
}
.modal-confirm .btn-delete:hover {
  transform: translateY(-1px);
  box-shadow: 0 8px 20px rgba(0,83,122,0.25);
}

/* Simple Themed View/Edit Modals */
.modal-themed .modal-content {
  border: none;
  border-radius: 15px;
  overflow: hidden;
}

.modal-header-themed {
  background: #2563eb;
  border-bottom: none;
  padding: 1.5rem 2rem;
}

.modal-title-themed {
  color: white;
  font-weight: 700;
  font-size: 1.2rem;
  margin: 0;
}

.modal-title-themed i {
  margin-right: 0.5rem;
}

.modal-header-themed .btn-close-white {
  filter: brightness(0) invert(1);
  opacity: 0.8;
}

.modal-body-themed {
  padding: 2rem;
  background: white;
}

.modal-footer-themed {
  background: #f8f9fa;
  border-top: 1px solid #dee2e6;
  padding: 1rem 2rem;
}

.btn-modal-close {
  background: #6c757d;
  color: white;
  border: none;
  padding: 0.5rem 1.5rem;
  border-radius: 8px;
  font-weight: 600;
}

.btn-modal-close:hover {
  background: #5a6268;
  color: white;
}

/* Simple Detail Styling */
.detail-item {
  margin-bottom: 1.5rem;
}

.detail-item:last-child {
  margin-bottom: 0;
}

.detail-label {
  font-weight: 700;
  color: #2c3e50;
  font-size: 0.9rem;
  margin-bottom: 0.25rem;
}

.detail-value {
  color: #495057;
  font-size: 1rem;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.5rem;
}

@media (max-width: 768px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }
}
          /* Success Checkmark Animation */
          .success-checkmark {
            width: 80px;
            height: 80px;
            margin: 0 auto;
          }
          .success-checkmark .check-icon {
            width: 80px;
            height: 80px;
            position: relative;
            border-radius: 50%;
            box-sizing: content-box;
            border: 4px solid #4CAF50;
          }
          .success-checkmark .check-icon::before {
            top: 3px;
            left: -2px;
            width: 30px;
            transform-origin: 100% 50%;
            border-radius: 100px 0 0 100px;
          }
          .success-checkmark .check-icon::after {
            top: 0;
            left: 30px;
            width: 60px;
            transform-origin: 0 50%;
            border-radius: 0 100px 100px 0;
            animation: rotate-circle 4.25s ease-in;
          }
          .success-checkmark .check-icon::before, .success-checkmark .check-icon::after {
            content: '';
            height: 100px;
            position: absolute;
            background: #FFFFFF;
            transform: rotate(-45deg);
          }
          .success-checkmark .check-icon .icon-line {
            height: 5px;
            background-color: #4CAF50;
            display: block;
            border-radius: 2px;
            position: absolute;
            z-index: 10;
          }
          .success-checkmark .check-icon .icon-line.line-tip {
            top: 46px;
            left: 14px;
            width: 25px;
            transform: rotate(45deg);
            animation: icon-line-tip 0.75s;
          }
          .success-checkmark .check-icon .icon-line.line-long {
            top: 38px;
            right: 8px;
            width: 47px;
            transform: rotate(-45deg);
            animation: icon-line-long 0.75s;
          }
          .success-checkmark .check-icon .icon-circle {
            top: -4px;
            left: -4px;
            z-index: 10;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            position: absolute;
            box-sizing: content-box;
            border: 4px solid rgba(76, 175, 80, .5);
          }
          .success-checkmark .check-icon .icon-fix {
            top: 8px;
            width: 5px;
            left: 26px;
            z-index: 1;
            height: 85px;
            position: absolute;
            transform: rotate(-45deg);
            background-color: #FFFFFF;
          }
          @keyframes icon-line-tip {
            0% { width: 0; left: 1px; top: 19px; }
            54% { width: 0; left: 1px; top: 19px; }
            70% { width: 50px; left: -8px; top: 37px; }
            84% { width: 17px; left: 21px; top: 48px; }
            100% { width: 25px; left: 14px; top: 45px; }
          }
          @keyframes icon-line-long {
            0% { width: 0; right: 46px; top: 54px; }
            65% { width: 0; right: 46px; top: 54px; }
            84% { width: 55px; right: 0px; top: 35px; }
            100% { width: 47px; right: 8px; top: 38px; }
          }
          @keyframes rotate-circle {
            0% { transform: rotate(-45deg); }
            5% { transform: rotate(-45deg); }
            12% { transform: rotate(-405deg); }
            100% { transform: rotate(-405deg); }
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
      
      .table-custom {
        font-size: 0.75rem;
      }
      
      .table-custom thead th,
      .table-custom tbody td {
        padding: 0.5rem;
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
          <a href="digital_record.php" class="nav-link active">
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
            <h5>Records</h5>
            <small>Complete transaction and service history</small>
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
                <i class="fa fa-file-alt"></i>
              </div>
              <div class="stat-info">
                <div class="stat-label">Total Records</div>
                <div class="stat-value"><?= number_format($totalRecords) ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6">
          <div class="stat-card">
            <div class="stat-card-content">
              <div class="stat-icon purple">
                <i class="fa fa-weight"></i>
              </div>
              <div class="stat-info">
                <div class="stat-label">Total Weight (kg)</div>
                <div class="stat-value"><?= number_format($totalWeight, 1) ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6">
          <div class="stat-card">
            <div class="stat-card-content">
              <div class="stat-icon success">
                <i class="fa fa-peso-sign"></i>
              </div>
              <div class="stat-info">
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value">₱<?= number_format($totalRevenue, 2) ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6">
          <div class="stat-card">
            <div class="stat-card-content">
              <div class="stat-icon warning">
                <i class="fa fa-clock"></i>
              </div>
              <div class="stat-info">
                <div class="stat-label">Pending Payments</div>
                <div class="stat-value"><?= number_format($pendingPayments) ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Search and Filter Card -->
      <div class="card-custom">
        <div class="card-header-custom">
          <div class="d-flex justify-content-between align-items-center">
            <h6><i class="fa fa-list me-2"></i>Transaction Records</h6>
            <button class="filter-toggle" onclick="toggleFilters()">
              <i class="fa fa-filter"></i>
              <span id="filterBtnText">Show Filters</span>
            </button>
          </div>
        </div>
        
        <div class="card-body-custom">
          <!-- Filter Section (Hidden by default) -->
          <div class="filter-section" id="filterSection" style="display: none;">
            <form method="GET" action="">
              <div class="row g-4">
                <div class="col-md-3">
                  <label>
                    <i class="fa fa-calendar-day"></i>
                    Specific Date
                  </label>
                  <div class="filter-input-wrapper">
                    <i class="fas fa-calendar-alt filter-input-icon"></i>
                    <input type="date" name="filter_date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>">
                  </div>
                </div>
                
                <div class="col-md-3">
                  <label>
                    <i class="fa fa-calendar"></i>
                    By Month
                  </label>
                  <div class="filter-input-wrapper">
                    <i class="fas fa-calendar-week filter-input-icon"></i>
                    <input type="month" name="filter_month" class="form-control" value="<?= htmlspecialchars($filterMonth) ?>">
                  </div>
                </div>
                
                <div class="col-md-3">
                  <label>
                    <i class="fa fa-money-bill"></i>
                    Payment Status
                  </label>
                  <div class="filter-input-wrapper">
                    <i class="fas fa-money-check-alt filter-input-icon"></i>
                    <select name="filter_payment" class="form-select">
                      <option value="">All Payments</option>
                      <option value="Paid" <?= $filterPaymentStatus === 'Paid' ? 'selected' : '' ?>>✓ Paid</option>
                      <option value="Unpaid" <?= $filterPaymentStatus === 'Unpaid' ? 'selected' : '' ?>>⏳ Unpaid</option>
                      <option value="Partial" <?= $filterPaymentStatus === 'Partial' ? 'selected' : '' ?>>⚠ Partial</option>
                    </select>
                  </div>
                </div>
                
                <div class="col-md-3">
                  <label>
                    <i class="fa fa-tasks"></i>
                    Laundry Status
                  </label>
                  <div class="filter-input-wrapper">
                    <i class="fas fa-spinner filter-input-icon"></i>
                    <select name="filter_laundry" class="form-select">
                      <option value="">All Status</option>
                                            <option value="Scheduled" <?= $filterLaundryStatus === 'Scheduled' ? 'selected' : '' ?>>📅 Scheduled</option>
                      <option value="PickedUp" <?= $filterLaundryStatus === 'PickedUp' ? 'selected' : '' ?>>🚚 PickedUp</option>
                      <option value="Processing" <?= $filterLaundryStatus === 'Processing' ? 'selected' : '' ?>>🔄 Processing</option>
                      <option value="Ready" <?= $filterLaundryStatus === 'Ready' ? 'selected' : '' ?>>✅ Ready</option>
                      <option value="Completed" <?= $filterLaundryStatus === 'Completed' ? 'selected' : '' ?>>🏁 Completed</option>
                    </select>
                  </div>
                </div>
              </div>
              
              <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn-filter">
                  <i class="fa fa-search"></i> Apply Filters
                </button>
                <a href="digital_record.php" class="btn-clear">
                  <i class="fa fa-times"></i> Clear All
                </a>
              </div>
            </form>
            
            <!-- Active Filters Display -->
            <?php if (!empty($filterDate) || !empty($filterMonth) || !empty($filterPaymentStatus) || !empty($filterLaundryStatus)): ?>
            <div class="active-filters">
              <strong style="color: var(--dark-blue); margin-right: 0.5rem;">Active Filters:</strong>
              <?php if (!empty($filterDate)): ?>
                <span class="filter-badge">
                  <i class="fa fa-calendar-day"></i> Date: <?= date('M d, Y', strtotime($filterDate)) ?>
                  <i class="fa fa-times-circle" onclick="removeFilter('filter_date')"></i>
                </span>
              <?php endif; ?>
              <?php if (!empty($filterMonth)): ?>
                <span class="filter-badge">
                  <i class="fa fa-calendar"></i> Month: <?= date('F Y', strtotime($filterMonth . '-01')) ?>
                  <i class="fa fa-times-circle" onclick="removeFilter('filter_month')"></i>
                </span>
              <?php endif; ?>
              <?php if (!empty($filterPaymentStatus)): ?>
                <span class="filter-badge">
                  <i class="fa fa-money-bill"></i> Payment: <?= htmlspecialchars($filterPaymentStatus) ?>
                  <i class="fa fa-times-circle" onclick="removeFilter('filter_payment')"></i>
                </span>
              <?php endif; ?>
              <?php if (!empty($filterLaundryStatus)): ?>
                <span class="filter-badge">
                  <i class="fa fa-tasks"></i> Status: <?= htmlspecialchars($filterLaundryStatus) ?>
                  <i class="fa fa-times-circle" onclick="removeFilter('filter_laundry')"></i>
                </span>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- Search Bar -->
          <div class="mb-4">
            <div class="search-bar">
              <i class="fa fa-search"></i>
              <input 
                type="text" 
                id="searchInput" 
                class="form-control" 
                placeholder="Search by Transaction ID, Customer, Service, Date, or Status..."
              >
            </div>
          </div>

          <!-- Table -->
          <?php if(!empty($records)): ?>
          <div class="table-responsive">
            <table class="table table-custom" id="recordsTable">
              <thead>
                <tr>
                  <th>Trans ID</th>
                  <th>Customer</th>
                  <th>Date</th>
                  <th>Time</th>
                  <th>Service</th>
                  <th>Add-ons</th>
                  <th>Type</th>
                  <th>Weight (kg)</th>
                  <th>Payment</th>
                  <th>Status</th>
                  <th>Laundry Status</th>
                  <th class="text-center">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($records as $r): ?>
                <tr data-transaction-id="<?= htmlspecialchars($r['Transaction_ID']) ?>">
                  <td class="cell-transid"><strong>#<?= htmlspecialchars($r['Transaction_ID']) ?></strong></td>
                  <td class="cell-customer"><?= htmlspecialchars($r['customer_name']) ?></td>
                  <td class="cell-date"><?= date('M d, Y', strtotime($r['date'])) ?></td>
                  <td class="cell-time"><?= date('h:i A', strtotime($r['time'])) ?></td>
                  <td class="cell-service"><?= htmlspecialchars($r['service']) ?></td>
                  <td class="cell-addons"><?= htmlspecialchars($r['add_ons'] ?: 'None') ?></td>
                  <td class="cell-type"><?= htmlspecialchars($r['pick_deliver']) ?></td>
                  <td class="cell-weight"><?= htmlspecialchars($r['laundry_weight']) ?></td>
                  <td class="cell-payment"><strong>₱<?= number_format($r['payment'], 2) ?></strong></td>
                  <td class="cell-payment-status">
                    <span class="badge-custom <?= strtolower($r['payment_status']) === 'paid' ? 'badge-paid' : 'badge-unpaid' ?>"><?= htmlspecialchars($r['payment_status']) ?></span>
                  </td>
                  <td class="cell-laundry-status">
                    <?php 
                       $status = $r['laundry_status'] ?? 'Scheduled';
                  $badgeClass = 'badge-scheduled';
                  if ($status === 'PickedUp') $badgeClass = 'badge-pickedup';
                  elseif ($status === 'Processing') $badgeClass = 'badge-processing';
                  elseif ($status === 'Ready') $badgeClass = 'badge-ready';
                  elseif ($status === 'Completed') $badgeClass = 'badge-completed';
                    ?>
                    <span class="badge-custom <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
                  </td>
                  <td>
                    <div class="action-btns">
                      <a href="view_record.php?id=<?= $r['Transaction_ID'] ?>" 
                         class="btn-action btn-info js-view" 
                         title="View Details"
                         data-view-url="view_record.php?id=<?= $r['Transaction_ID'] ?>">
                        <i class="fas fa-eye"></i>
                      </a>
                      <a href="edit_record.php?id=<?= $r['Transaction_ID'] ?>" 
                         class="btn-action btn-warning js-edit" 
                         title="Edit Record"
                         data-edit-url="edit_record.php?id=<?= $r['Transaction_ID'] ?>">
                        <i class="fas fa-edit"></i>
                      </a>
                      <a href="delete_record.php?id=<?= $r['Transaction_ID'] ?>" 
                         class="btn-action btn-danger js-delete" 
                         title="Delete Record"
                         data-delete-url="delete_record.php?id=<?= $r['Transaction_ID'] ?>">
                        <i class="fas fa-trash-alt"></i>
                      </a>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
          <div class="text-center py-5">
            <i class="fa fa-database" style="font-size: 4rem; color: var(--light-blue); margin-bottom: 1rem;"></i>
            <h5 class="text-muted">No records found</h5>
            <p class="text-muted">
              <?php if (!empty($filterDate) || !empty($filterMonth) || !empty($filterPaymentStatus) || !empty($filterLaundryStatus)): ?>
                Try adjusting your filters or search terms
              <?php else: ?>
                Transaction records will appear here once created
              <?php endif; ?>
            </p>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <footer>
        <p class="mb-0">© <?= date('Y') ?> <strong>MangTV Laundry Shop</strong> - All Rights Reserved</p>
      </footer>

    </div>
  </main>

<!-- View Record Modal -->
  <div class="modal fade" id="viewRecordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content modal-themed">
        <div class="modal-header modal-header-themed">
          <h5 class="modal-title modal-title-themed">
            <i class="fa fa-eye me-2"></i>Transaction Details
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body modal-body-themed" id="viewRecordModalBody">
          <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
          </div>
        </div>
        
      </div>
    </div>
  </div>

  <!-- Edit Record Modal -->
  <div class="modal fade" id="editRecordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content modal-themed">
        <div class="modal-header modal-header-themed">
          <h5 class="modal-title modal-title-themed">
            <i class="fa fa-edit me-2"></i>Edit Transaction Record
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body modal-body-themed" id="editRecordModalBody">
          <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content modal-confirm">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fa fa-triangle-exclamation me-2 text-danger"></i>Confirm Deletion</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          Are you sure you want to delete this record? This action cannot be undone.
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <form id="deleteConfirmForm" method="post" class="d-inline">
            <input type="hidden" name="_method" value="delete">
            <button type="submit" class="btn btn-delete">
              <i class="fa fa-trash me-1"></i>Delete
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

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


    // Filter toggle
    function toggleFilters() {
      const filterSection = document.getElementById('filterSection');
      const filterBtn = document.getElementById('filterBtnText');
      
      if (filterSection.style.display === 'none') {
        filterSection.style.display = 'block';
        filterBtn.textContent = 'Hide Filters';
      } else {
        filterSection.style.display = 'none';
        filterBtn.textContent = 'Show Filters';
      }
    }
    // Remove individual filter
    function removeFilter(filterName) {
      const url = new URL(window.location.href);
      url.searchParams.delete(filterName);
      window.location.href = url.toString();
    }
    // Auto-show filters if any are active
    <?php if (!empty($filterDate) || !empty($filterMonth) || !empty($filterPaymentStatus) || !empty($filterLaundryStatus)): ?>
    window.addEventListener('DOMContentLoaded', function() {
      toggleFilters();
    });
    <?php endif; ?>
    // Client-side search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
      searchInput.addEventListener('keyup', function() {
        const filter = searchInput.value.toLowerCase();
        const table = document.getElementById('recordsTable');
        if (table) {
          const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
          
          for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const text = row.textContent.toLowerCase();
            
            if (text.includes(filter)) {
              row.style.display = '';
            } else {
              row.style.display = 'none';
            }
          }
        }
      });
    }
  </script>
  <script>
   // Replaces the previous "Delete confirmation modal logic" block.
// This version sends a POST (AJAX) to delete_record.php and removes the table row on success.
// It still supports form-target deletes (submits form data) and link-target deletes (extracts id query param).
// Requires: a modal with id="deleteConfirmModal" and form with id="deleteConfirmForm" in the page.

const drDeleteModalEl = document.getElementById('deleteConfirmModal');
const drDeleteForm = document.getElementById('deleteConfirmForm');
const drDeleteModal = (typeof bootstrap !== 'undefined' && drDeleteModalEl) ? new bootstrap.Modal(drDeleteModalEl) : null;

let drDeleteTarget = { type: 'link', href: null, form: null };

function drHandleDeleteTrigger(e) {
  e.preventDefault();
  const trigger = e.currentTarget;
  const href = trigger.getAttribute('data-delete-url') || trigger.getAttribute('href') || null;
  const formSelector = trigger.getAttribute('data-delete-form') || null;

  if (formSelector) {
    const form = document.querySelector(formSelector);
    if (form) drDeleteTarget = { type: 'form', href: null, form };
    else drDeleteTarget = { type: 'link', href, form: null };
  } else if (href) {
    drDeleteTarget = { type: 'link', href, form: null };
  } else {
    drDeleteTarget = { type: 'link', href: null, form: null };
  }

  if (!drDeleteModal || !drDeleteForm) return;

  // Attach the AJAX submit handler (will be used when user confirms)
  // We set .onsubmit each time so the closure captures latest drDeleteTarget
  drDeleteForm.onsubmit = async function(ev) {
    ev.preventDefault();
    // disable submit button to avoid double submits
    const submitBtn = drDeleteForm.querySelector('[type="submit"]') || drDeleteForm.querySelector('button');
    if (submitBtn) { submitBtn.disabled = true; }

    try {
      // Determine action and payload
      let actionUrl = 'delete_record.php';
      let bodyParams = new URLSearchParams();

      if (drDeleteTarget.type === 'form' && drDeleteTarget.form) {
        const formEl = drDeleteTarget.form;
        // If the target form has an action, use it; otherwise use default actionUrl
        const formAction = formEl.getAttribute('action');
        if (formAction) {
          // If the action is a full URL or path, use it
          actionUrl = formAction;
        }
        // Collect form fields
        const fd = new FormData(formEl);
        for (const [k, v] of fd.entries()) bodyParams.append(k, v);
        // If there's no id in form, try to get id from action href or dataset
        if (!bodyParams.has('id')) {
          const idFromHref = (formAction && new URL(formAction, window.location.origin).searchParams.get('id')) || null;
          if (idFromHref) bodyParams.append('id', idFromHref);
        }
      } else if (drDeleteTarget.type === 'link' && drDeleteTarget.href) {
        // If the link contains an id param, extract it
        const urlObj = new URL(drDeleteTarget.href, window.location.origin);
        const id = urlObj.searchParams.get('id');
        if (id) bodyParams.append('id', id);
        // Use delete_record.php as endpoint (keeps URL structure consistent)
        // If you prefer to POST to the exact link path, set actionUrl = urlObj.pathname;
      } else {
        // Nothing to delete
        throw new Error('No delete target found.');
      }

      // If no id present, fallback to redirect (safer than blind POST)
      if (!bodyParams.has('id')) {
        // fallback: if href exists, navigate to it (may perform legacy behavior)
        if (drDeleteTarget.href) {
          window.location.href = drDeleteTarget.href;
          return;
        }
        throw new Error('Missing id for deletion.');
      }

      // Send POST request with proper headers
      const res = await fetch(actionUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 
          'X-Requested-With': 'XMLHttpRequest',
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: bodyParams.toString()
      });

      // Try to parse as JSON first
      let responseData;
      try {
        const text = await res.text();
        console.log('Delete response text:', text); // Debug log
        console.log('Response status:', res.status); // Debug log
        console.log('Response headers:', res.headers.get('Content-Type')); // Debug log
        
        // Try to parse as JSON
        try {
          responseData = JSON.parse(text);
          console.log('Parsed JSON:', responseData); // Debug log
        } catch (e) {
          console.log('Not JSON, treating as text/HTML'); // Debug log
          // Not JSON, treat as HTML/text
          responseData = { isHtml: true, content: text };
        }
      } catch (err) {
        console.error('Error reading response:', err);
        drDeleteModal.hide();
        showTemporaryAlert('Error processing delete response', 'danger');
        return;
      }

      if (res.ok) {
        const id = bodyParams.get('id');
        const row = document.querySelector(`tr[data-transaction-id="${id}"]`);
        
        console.log('Response is OK, checking success flag...'); // Debug log
        
        // Check if it's JSON with success flag
        if (responseData.success !== undefined) {
          console.log('Success flag found:', responseData.success); // Debug log
          if (responseData.success) {
            // Remove row from table
            if (row) {
              console.log('Removing row from table'); // Debug log
              row.remove();
            }
            
            // Hide confirmation modal and show success modal
            drDeleteModal.hide();
            console.log('Showing success modal'); // Debug log
            showDeleteSuccessModal(responseData.message || 'Record has been successfully deleted from the system.');
          } else {
            drDeleteModal.hide();
            showTemporaryAlert(responseData.message || 'Delete failed', 'danger');
          }
        } else {
          console.log('No success flag, assuming success'); // Debug log
          // Not JSON or no success flag - assume success if HTTP 200 and remove row
          if (row) row.remove();
          
          drDeleteModal.hide();
          showDeleteSuccessModal('Record has been successfully deleted from the system.');
        }
        return;
      } else {
        // HTTP error
        console.log('HTTP error response'); // Debug log
        drDeleteModal.hide();
        showTemporaryAlert(responseData.message || responseData.content || 'Delete failed', 'danger');
        return;
      }
    } catch (err) {
      console.error('Delete request error:', err);
      drDeleteModal.hide();
      showTemporaryAlert(err.message || 'Unexpected error', 'danger');
      return;
    } finally {
      if (submitBtn) submitBtn.disabled = false;
    }
  };

  // Show confirmation modal
  drDeleteModal.show();
}

function drBindDeleteTriggers() {
  const triggers = document.querySelectorAll('[data-confirm="delete"], .js-delete, a[data-delete-url], button[data-delete-url]');
  triggers.forEach(el => {
    if (!el.dataset.deleteBound) {
      el.addEventListener('click', drHandleDeleteTrigger);
      el.dataset.deleteBound = '1';
    }
  });
}

// small helper for temporary alerts (used for errors)
function showTemporaryAlert(message, type = 'success', duration = 3000) {
  const el = document.createElement('div');
  el.className = `alert alert-${type} position-fixed`;
  el.style.top = '1rem';
  el.style.right = '1rem';
  el.style.zIndex = 12000;
  el.style.minWidth = '160px';
  el.textContent = message;
  document.body.appendChild(el);
  setTimeout(() => { el.style.transition = 'opacity 0.6s'; el.style.opacity = '0'; }, duration - 600);
  setTimeout(() => el.remove(), duration);
}

// Success Modal for Delete Operation
function showDeleteSuccessModal(message) {
  const modal = `
    <div class="modal fade" id="deleteSuccessModal" tabindex="-1" data-bs-backdrop="static">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
          <div class="modal-body text-center py-5 px-4" style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);">
            <div class="mb-4">
              <div class="success-checkmark">
                <div class="check-icon">
                  <span class="icon-line line-tip"></span>
                  <span class="icon-line line-long"></span>
                  <div class="icon-circle"></div>
                  <div class="icon-fix"></div>
                </div>
              </div>
            </div>
            <h4 class="mb-3 fw-bold" style="color: #00537A;">Deleted Successfully!</h4>
            <p class="text-muted mb-4">${message || 'The record has been deleted from the system.'}</p>
            <button type="button" class="btn px-5 py-2" onclick="closeDeleteSuccessModal()" style="background: linear-gradient(135deg, #00537A 0%, #006b99 100%); color: white; border: none; border-radius: 10px; font-weight: 600; box-shadow: 0 4px 12px rgba(0,83,122,0.3);">
              <i class="fa fa-check me-2"></i>OK
            </button>
          </div>
        </div>
      </div>
    </div>
  `;
  
  document.body.insertAdjacentHTML('beforeend', modal);
  const deleteSuccessModalEl = document.getElementById('deleteSuccessModal');
  const deleteSuccessModalInstance = new bootstrap.Modal(deleteSuccessModalEl);
  deleteSuccessModalInstance.show();
}

// Close delete success modal
function closeDeleteSuccessModal() {
  const deleteSuccessModalEl = document.getElementById('deleteSuccessModal');
  if (deleteSuccessModalEl) {
    const deleteSuccessModalInstance = bootstrap.Modal.getInstance(deleteSuccessModalEl);
    if (deleteSuccessModalInstance) {
      deleteSuccessModalInstance.hide();
    }
    deleteSuccessModalEl.addEventListener('hidden.bs.modal', function() {
      deleteSuccessModalEl.remove();
    });
  }
}

// initial bind
drBindDeleteTriggers();
document.addEventListener('DOMContentLoaded', drBindDeleteTriggers);
document.addEventListener('htmx:afterSwap', drBindDeleteTriggers);

// initial bind
drBindDeleteTriggers();
document.addEventListener('DOMContentLoaded', drBindDeleteTriggers);
document.addEventListener('htmx:afterSwap', drBindDeleteTriggers);

    // ---------- View & Edit Modal Handlers ----------
    const viewModalEl = document.getElementById('viewRecordModal');
    const viewModalBody = document.getElementById('viewRecordModalBody');
    const viewModal = (typeof bootstrap !== 'undefined' && viewModalEl) ? new bootstrap.Modal(viewModalEl) : null;

    const editModalEl = document.getElementById('editRecordModal');
    const editModalBody = document.getElementById('editRecordModalBody');
    const editModal = (typeof bootstrap !== 'undefined' && editModalEl) ? new bootstrap.Modal(editModalEl) : null;

    // Generic function to fetch HTML and inject into a container
    async function fetchHtmlIntoContainer(url, container, onLoaded) {
      container.innerHTML = `<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>`;
      try {
        const res = await fetch(url, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('Network response was not ok');
        const html = await res.text();
        container.innerHTML = html;
        // re-bind any triggers inside the injected fragment
        drBindDeleteTriggers();
        bindViewEditTriggers();
        if (typeof onLoaded === 'function') onLoaded();
      } catch (err) {
        console.error('Error fetching HTML:', err);
        container.innerHTML = `<div class="alert alert-danger">Error loading content. Please try again.</div>`;
      }
    }

    // View click handler
    function handleViewClick(e) {
      e.preventDefault();
      const el = e.currentTarget;
      const url = el.getAttribute('data-view-url') || el.getAttribute('href');
      if (!url) return;
      if (!viewModal) {
        // fallback: navigate
        window.location.href = url;
        return;
      }
      fetchHtmlIntoContainer(url, viewModalBody);
      viewModal.show();
    }

    // Update a table row using form values (no extra fetch required)
    function updateTableRowFromForm(form) {
      try {
        const formData = new FormData(form);
        const id = formData.get('id') || formData.get('Transaction_ID');
        if (!id) return;

        const row = document.querySelector(`tr[data-transaction-id="${id}"]`);
        if (!row) return;

        // Map values (the edit form uses these names)
        const date = formData.get('date') || '';
        const time = formData.get('time') || '';
        const service = formData.get('service') || '';
        const add_ons = formData.get('add_ons') || '';
        const pick_deliver = formData.get('pick_deliver') || '';
        const laundry_weight = formData.get('laundry_weight') || '';
        const payment = formData.get('payment') || '';
        const payment_status = formData.get('payment_status') || '';
        const laundry_status = formData.get('laundry_status') || '';

        // Update cells (ensure formatting similar to table)
        const cellDate = row.querySelector('.cell-date');
        const cellTime = row.querySelector('.cell-time');
        const cellService = row.querySelector('.cell-service');
        const cellAddons = row.querySelector('.cell-addons');
        const cellType = row.querySelector('.cell-type');
        const cellWeight = row.querySelector('.cell-weight');
        const cellPayment = row.querySelector('.cell-payment');
        const cellPaymentStatus = row.querySelector('.cell-payment-status');
        const cellLaundryStatus = row.querySelector('.cell-laundry-status');

        if (cellDate) {
          // Display date in "M d, Y" if possible
          if (date) {
            const d = new Date(date);
            if (!isNaN(d)) {
              const opts = { month: 'short', day: '2-digit', year: 'numeric' };
              cellDate.textContent = d.toLocaleDateString(undefined, opts);
            } else {
              cellDate.textContent = date;
            }
          }
        }
        if (cellTime) {
          if (time) {
            // convert "HH:MM" to 12hr format (depends on browser)
            const tParts = time.split(':');
            if (tParts.length >= 2) {
              let hh = parseInt(tParts[0], 10);
              const mm = tParts[1];
              const ampm = hh >= 12 ? 'PM' : 'AM';
              hh = ((hh + 11) % 12) + 1;
              cellTime.textContent = `${hh.toString().padStart(2,'0')}:${mm} ${ampm}`;
            } else {
              cellTime.textContent = time;
            }
          }
        }
        if (cellService) cellService.textContent = service;
        if (cellAddons) cellAddons.textContent = add_ons || 'None';
        if (cellType) cellType.textContent = pick_deliver;
        if (cellWeight) cellWeight.textContent = laundry_weight;

        if (cellPayment) {
          const formatted = parseFloat(payment || 0).toFixed(2);
          cellPayment.innerHTML = `<strong>₱${Number(formatted).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</strong>`;
        }

        if (cellPaymentStatus) {
          const ps = payment_status.trim();
          const span = document.createElement('span');
          span.className = 'badge-custom ' + (ps.toLowerCase() === 'paid' ? 'badge-paid' : 'badge-unpaid');
          span.textContent = ps;
          cellPaymentStatus.innerHTML = '';
          cellPaymentStatus.appendChild(span);
        }

        if (cellLaundryStatus) {
          const ls = laundry_status.trim() || 'Waiting';
          let cls = 'badge-waiting';
          if (ls === 'Completed') cls = 'badge-completed';
          else if (ls === 'Processing') cls = 'badge-processing';
          const span2 = document.createElement('span');
          span2.className = 'badge-custom ' + cls;
          span2.textContent = ls;
          cellLaundryStatus.innerHTML = '';
          cellLaundryStatus.appendChild(span2);
        }

      } catch (err) {
        console.error('Error updating table row:', err);
      }
    }

     // Edit click handler
    function handleEditClick(e) {
      e.preventDefault();
      const el = e.currentTarget;
      const url = el.getAttribute('data-edit-url') || el.getAttribute('href');
      if (!url) return;
      if (!editModal) {
        // fallback: navigate
        window.location.href = url;
        return;
      }
      fetchHtmlIntoContainer(url, editModalBody, () => {
        // After loaded, try to find a form inside the injected HTML and intercept it for AJAX submission
        const form = editModalBody.querySelector('form');
        if (form) {
          // Avoid double-binding
          if (!form.dataset.ajaxBound) {
            form.dataset.ajaxBound = '1';
            form.addEventListener('submit', async function(ev) {
              ev.preventDefault();
              const submitBtn = form.querySelector('[type="submit"]');
              const originalText = submitBtn ? submitBtn.innerHTML : null;
              if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
              }

              try {
                const formData = new FormData(form);
                const action = form.getAttribute('action') || url;
                const method = (form.getAttribute('method') || 'POST').toUpperCase();

                const res = await fetch(action, {
                  method,
                  body: formData,
                  credentials: 'same-origin',
                  headers: { 'X-Requested-With': 'XMLHttpRequest' } // signal AJAX
                });

                const contentType = res.headers.get('Content-Type') || '';

                if (res.ok) {
                  if (contentType.indexOf('application/json') !== -1) {
                    const json = await res.json();
                    if (json.success) {
                      // Update the table using values from the form (no extra fetch)
                      updateTableRowFromForm(form);

                      // Close edit modal
                      editModal.hide();

                      // Show success modal instead of alert
                      showSuccessModal(json.message || 'Record updated successfully!', json.data);

                      return;
                    } else {
                      // server returned success:false
                      showTemporaryAlert(json.message || 'Failed to update.', 'danger');
                      // If server returned updated HTML or form, replace modal body
                      if (json.html) editModalBody.innerHTML = json.html;
                      return;
                    }
                  } else {
                    // Non-JSON: assume HTML (validation errors or refreshed form) — replace modal body
                    const text = await res.text();
                    editModalBody.innerHTML = text;
                    // rebind on new form
                    const newForm = editModalBody.querySelector('form');
                    if (newForm) {
                      // let bind run again by clearing dataset flag
                      newForm.dataset.ajaxBound = '';
                      // call our onLoaded callback by recursion
                      // but to avoid infinite loops, simply re-run the handler binder
                      // (will be bound when user clicks save)
                    }
                  }
                } else {
                  // res not ok: show returned message or text
                  const text = await res.text();
                  editModalBody.innerHTML = text;
                }
              } catch (err) {
                console.error('Error submitting edit form:', err);
                const alertEl = document.createElement('div');
                alertEl.className = 'alert alert-danger';
                alertEl.textContent = 'An error occurred while saving. Please try again.';
                editModalBody.prepend(alertEl);
              } finally {
                if (submitBtn) {
                  submitBtn.disabled = false;
                  if (originalText) submitBtn.innerHTML = originalText;
                }
              }
            });
          }
        }
      });
      editModal.show();
    }

   
    // Success Modal Function
    function showSuccessModal(message, data) {
      const modal = `
        <div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
              <div class="modal-body text-center py-5 px-4" style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);">
                <div class="mb-4">
                  <div class="success-checkmark">
                    <div class="check-icon">
                      <span class="icon-line line-tip"></span>
                      <span class="icon-line line-long"></span>
                      <div class="icon-circle"></div>
                      <div class="icon-fix"></div>
                    </div>
                  </div>
                </div>
                <h4 class="mb-3 fw-bold" style="color: #00537A;">Success!</h4>
                <p class="text-muted mb-4">${message}</p>
                <button type="button" class="btn px-5 py-2" onclick="closeSuccessModal()" style="background: linear-gradient(135deg, #00537A 0%, #006b99 100%); color: white; border: none; border-radius: 10px; font-weight: 600; box-shadow: 0 4px 12px rgba(0,83,122,0.3);">
                  <i class="fa fa-check me-2"></i>OK
                </button>
              </div>
            </div>
          </div>
        </div>
        
      `;
      
      document.body.insertAdjacentHTML('beforeend', modal);
      const successModalEl = document.getElementById('successModal');
      const successModalInstance = new bootstrap.Modal(successModalEl);
      successModalInstance.show();
    }

    // Close success modal and reload
    function closeSuccessModal() {
      const successModalEl = document.getElementById('successModal');
      if (successModalEl) {
        const successModalInstance = bootstrap.Modal.getInstance(successModalEl);
        if (successModalInstance) {
          successModalInstance.hide();
        }
        successModalEl.addEventListener('hidden.bs.modal', function() {
          successModalEl.remove();
          location.reload();
        });
      }
    }
    
    function bindViewEditTriggers() {
      // View
      document.querySelectorAll('.js-view, a[data-view-url]').forEach(el => {
        if (!el.dataset.viewBound) {
          el.addEventListener('click', handleViewClick);
          el.dataset.viewBound = '1';
        }
      });
      // Edit
      document.querySelectorAll('.js-edit, a[data-edit-url]').forEach(el => {
        if (!el.dataset.editBound) {
          el.addEventListener('click', handleEditClick);
          el.dataset.editBound = '1';
        }
      });
    }

    // Initial bindings
    drBindDeleteTriggers();
    bindViewEditTriggers();
    document.addEventListener('DOMContentLoaded', function() {
      drBindDeleteTriggers();
      bindViewEditTriggers();
    });
    document.addEventListener('htmx:afterSwap', function() {
      drBindDeleteTriggers();
      bindViewEditTriggers();
    });
  </script>
</body>
</html>
<?php $conn->close(); ?>