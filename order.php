<?php
// order_scheduling.php — MangTV Order & Scheduling (matching dashboard design)
session_start();
include 'db_connect.php';

// Include notification helpers if file exists
if (file_exists('notification_admin_helpers.php')) {
    include 'notification_admin_helpers.php';
    $unreadNotifCount = getUnreadCount($conn);
} else {
    $unreadNotifCount = 0; // Default if notification system not set up yet
}

// --- Handle Confirmation / Update / Delete ---
$successMessage = '';
$successType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Confirm (Approve / Reject)
    if (isset($_POST['confirm'])) {
        $scheduleID = intval($_POST['schedule_id']);
        $status = $_POST['confirm'];

        $stmt = $conn->prepare("UPDATE schedule SET admin_confirmation=? WHERE Schedule_ID=?");
        $stmt->bind_param("si", $status, $scheduleID);
        if ($stmt->execute()) {
            $successMessage = "Schedule " . strtolower($status) . ".";
            $successType = $status === 'Approved' ? 'approve' : 'reject';
        }
    }

    // Update Schedule
    if (isset($_POST['update'])) {
        $scheduleID = intval($_POST['schedule_id']);
        $date = $_POST['date'];
        $time = $_POST['time'];
        $service = $_POST['service'];
        $add_ons = $_POST['add_ons'];
        $pick_deliver = $_POST['pick_deliver'];

        $stmt = $conn->prepare("UPDATE schedule SET date=?, time=?, service=?, add_ons=?, pick_deliver=? WHERE Schedule_ID=?");
        $stmt->bind_param("sssssi", $date, $time, $service, $add_ons, $pick_deliver, $scheduleID);
        if ($stmt->execute()) {
            $successMessage = "Schedule updated.";
            $successType = 'update';
        }
    }

    // Delete Schedule
    if (isset($_POST['delete'])) {
        $scheduleID = intval($_POST['schedule_id']);
        
        // First, delete related records in order (child tables first)
        // Delete tracking records
        $stmt = $conn->prepare("DELETE FROM tracking WHERE Schedule_ID=?");
        $stmt->bind_param("i", $scheduleID);
        $stmt->execute();
        
        // Delete transaction records
        $stmt = $conn->prepare("DELETE FROM transaction WHERE Schedule_ID=?");
        $stmt->bind_param("i", $scheduleID);
        $stmt->execute();
        
        // Then delete the schedule
        $stmt = $conn->prepare("DELETE FROM schedule WHERE Schedule_ID=?");
        $stmt->bind_param("i", $scheduleID);
        if ($stmt->execute()) {
            $successMessage = "Schedule deleted.";
            $successType = 'delete';
        }
    }
}

// Get success message from session
if (isset($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    $successType = $_SESSION['success_type'];
    unset($_SESSION['success_message']);
    unset($_SESSION['success_type']);
}

// --- Filters ---
$filterStatus = $_GET['status'] ?? '';

// --- Fetch Schedules ---
$schedules = [];
$whereConditions = [];
if (!empty($filterStatus)) {
    if ($filterStatus === 'Pending') {
        $whereConditions[] = "(s.admin_confirmation IS NULL OR s.admin_confirmation = 'Pending')";
    } elseif (in_array($filterStatus, ['Approved', 'Rejected'])) {
        $whereConditions[] = "s.admin_confirmation = '" . $conn->real_escape_string($filterStatus) . "'";
    }
}
$whereClause = !empty($whereConditions) ? ('WHERE ' . implode(' AND ', $whereConditions)) : '';

$query = "SELECT s.*, CONCAT(c.first_name,' ',c.last_name) AS customer_name, c.contact_number, c.email
          FROM schedule s
          JOIN customer_info c ON s.Customer_ID=c.Customer_ID
          $whereClause
          ORDER BY s.date DESC, s.time DESC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
}

// Get statistics
$totalSchedules = count($schedules);
$approvedCount = count(array_filter($schedules, function($s) { return $s['admin_confirmation'] === 'Approved'; }));
$pendingCount = count(array_filter($schedules, function($s) { return $s['admin_confirmation'] === 'Pending'; }));
$rejectedCount = count(array_filter($schedules, function($s) { return $s['admin_confirmation'] === 'Rejected'; }));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>MangTV - Order & Scheduling</title>

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
    
    .stat-icon.success { 
      background: linear-gradient(135deg, #198754 0%, #20c997 100%);
    }
    
    .stat-icon.warning { 
      background: linear-gradient(135deg, #fd7e14 0%, #ffc107 100%);
    }
    
    .stat-icon.danger { 
      background: linear-gradient(135deg, #dc3545 0%, #ff6b6b 100%);
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
    
    /* Schedule Cards */
    .schedule-card {
      background: white;
      border-radius: 12px;
      padding: 1.25rem;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      border: 1px solid rgba(168,232,249,0.2);
      transition: all 0.3s;
      height: 100%;
      position: relative;
      overflow: hidden;
    }
    
    .schedule-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 4px;
      height: 100%;
      background: var(--dark-blue);
    }
    
    .schedule-card.approved::before {
      background: #198754;
    }
    
    .schedule-card.rejected::before {
      background: #dc3545;
    }
    
    .schedule-card.pending::before {
      background: #fd7e14;
    }
    
    .schedule-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }
    
    .schedule-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1rem;
      padding-bottom: 0.75rem;
      border-bottom: 2px solid rgba(168,232,249,0.3);
    }
    
    .schedule-id {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--dark-blue);
    }
    
    .status-badge {
      padding: 0.4rem 0.75rem;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.75rem;
      letter-spacing: 0.5px;
    }
    
    .status-badge.approved {
      background: #d1e7dd;
      color: #0f5132;
    }
    
    .status-badge.pending {
      background: #fff3cd;
      color: #856404;
    }
    
    .status-badge.rejected {
      background: #f8d7da;
      color: #721c24;
    }
    
    .schedule-info {
      margin-bottom: 0.75rem;
    }
    
    .info-row {
      display: flex;
      align-items: start;
      gap: 0.5rem;
      margin-bottom: 0.5rem;
      font-size: 0.9rem;
    }
    
    .info-row i {
      width: 20px;
      color: var(--dark-blue);
      margin-top: 2px;
    }
    
    .info-row strong {
      min-width: 80px;
      color: #6c757d;
      font-weight: 600;
    }
    
    .schedule-actions {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
      margin-top: 1rem;
      padding-top: 1rem;
      border-top: 1px solid rgba(168,232,249,0.2);
    }
    
      .btn-schedule {
      flex: 1;
      min-width: 100px;
      padding: 0.5rem 0.75rem;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.85rem;
      border: none;
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.4rem;
    }
    .btn-schedule:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .btn-approve {
      background: linear-gradient(135deg, #198754 0%, #20c997 100%);
      color: white;
    }
    .btn-reject {
      background: linear-gradient(135deg, #dc3545 0%, #ff6b6b 100%);
      color: white;
    }
    .btn-edit { background: var(--light-blue); color: var(--dark-blue); }
    .btn-delete {
      background: transparent;
      color: #dc3545;
      border: 2px solid #dc3545;
    }

    /* Generic modal styling (matches palette) */
    .modal-content {
      border-radius: 16px;
      border: none;
      overflow: hidden;
    }
    .modal-header {
      background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
      color: white;
      padding: 1.1rem 1.4rem;
      border: none;
    }
    .modal-header .btn-close {
      filter: brightness(0) invert(1);
    }
    .modal-body {
      padding: 1.3rem 1.4rem;
      color: var(--text-dark);
    }
    .modal-footer {
      padding: 0.9rem 1.4rem;
      border-top: 1px solid rgba(168,232,249,0.3);
    }

    /* Themed delete confirmation modal (your existing) */
    .modal-confirm .modal-header {
      background: linear-gradient(135deg, var(--light-blue) 0%, rgba(168,232,249,0.3) 100%);
      border-bottom: 1px solid rgba(168,232,249,0.3);
      color: var(--dark-blue);
    }
    .modal-confirm .modal-title { font-weight: 700; color: var(--dark-blue); }
    .modal-confirm .modal-body { color: var(--text-dark); }
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

    /* Success Modal */
    .success-modal .modal-content {
      border: none;
      box-shadow: 0 10px 40px rgba(0,0,0,0.15);
      border-radius: 20px;
      overflow: hidden;
    }
    
    .success-modal .modal-header {
      border: none;
      padding: 0;
      display: none;
    }
    
    .success-modal .modal-body {
      padding: 3rem 2rem 2rem;
      text-align: center;
    }
    
    .success-modal .success-icon {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
      font-size: 3rem;
      animation: successPop 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
      border: 4px solid;
    }
    
    .success-modal .success-icon.approve {
      background: rgba(25, 135, 84, 0.1);
      color: #198754;
      border-color: #198754;
    }
    
    .success-modal .success-icon.reject {
      background: rgba(220, 53, 69, 0.1);
      color: #dc3545;
      border-color: #dc3545;
    }
    
    .success-modal .success-icon.update {
      background: rgba(0, 83, 122, 0.1);
      color: var(--dark-blue);
      border-color: var(--dark-blue);
    }
    
    .success-modal .success-icon.delete {
      background: rgba(108, 117, 125, 0.1);
      color: #6c757d;
      border-color: #6c757d;
    }
    
    @keyframes successPop {
      0% {
        transform: scale(0) rotate(-180deg);
        opacity: 0;
      }
      50% {
        transform: scale(1.1) rotate(10deg);
      }
      100% {
        transform: scale(1) rotate(0deg);
        opacity: 1;
      }
    }
    
    .success-modal .success-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--dark-blue);
      margin-bottom: 0.75rem;
    }
    
    .success-modal .success-message {
      font-size: 0.95rem;
      color: #6c757d;
      margin-bottom: 0;
      line-height: 1.5;
    }
    
    .success-modal .modal-footer {
      border: none;
      padding: 1rem 2rem 2rem;
      justify-content: center;
    }
    
    .success-modal .btn-success-ok {
      background: var(--dark-blue);
      color: white;
      border: none;
      padding: 0.65rem 3rem;
      border-radius: 8px;
      font-weight: 700;
      font-size: 0.95rem;
      transition: all 0.3s;
      min-width: 120px;
    }
    
    .success-modal .btn-success-ok:hover {
      background: #006b99;
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(0,83,122,0.3);
    }
    
    .success-modal .btn-success-ok i {
      margin-right: 0.5rem;
    }

    /* Filter section */
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
      font-size: 0.85rem;
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
      font-size: 0.85rem;
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
      font-size: 0.85rem;
    }
    .btn-clear:hover {
      background: #dc3545;
      color: #fff;
      transform: translateY(-2px);
    }

    footer {
      background: white;
      padding: 1.5rem 0;
      margin-top: 3rem;
      text-align: center;
      color: #6c757d;
      border-top: 1px solid rgba(168,232,249,0.3);
      font-size: 0.9rem;
    }

    @media (max-width:992px) {
      .sidebar { width: var(--sidebar-collapsed); }
      .sidebar .brand-text,
      .sidebar .nav-text,
      .sidebar .nav-section-title {
        opacity: 0;
        visibility: hidden;
      }
      main { margin-left: var(--sidebar-collapsed); }
      .notification-dropdown {
        width: 90vw;
        right: -150px;
      }
    }
    @media (max-width:768px) {
      .topbar { padding: 1rem; }
      .stat-card { margin-bottom: 1rem; }
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
          <a href="order_scheduling.php" class="nav-link active">
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
          <a href="admin_feedback.php" class="nav-link">
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
            <h5>Order & Scheduling</h5>
            <small>Manage customer orders and schedules</small>
          </div>
        </div>
        <div class="topbar-actions d-flex align-items-center gap-3">
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
                <h6>Notifications</h6>
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
                  Tap a notification to view details
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
        <h6 class="m-0" style="color: var(--dark-blue); font-weight: 700;">Schedules</h6>
        <button class="filter-toggle" type="button" onclick="toggleStatusFilter()">
          <i class="fa fa-filter"></i> <span id="filterBtnText">Show Filters</span>
        </button>
      </div>

      <div id="statusFilter" class="filter-section" style="display: none;">
        <form method="GET" action="">
          <div class="row g-3 align-items-end">
            <div class="col-sm-6 col-md-4 col-lg-3">
              <label class="form-label" style="font-weight:600; color: var(--dark-blue);">Filter by Status</label>
              <select name="status" class="form-select">
                <option value="">All</option>
                <option value="Pending" <?= $filterStatus === 'Pending' ? 'selected' : '' ?>>Pending</option>
                <option value="Approved" <?= $filterStatus === 'Approved' ? 'selected' : '' ?>>Approved</option>
                <option value="Rejected" <?= $filterStatus === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
              </select>
            </div>
            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn-filter"><i class="fa fa-search me-1"></i>Apply</button>
              <a href="order_scheduling.php" class="btn-clear">Clear</a>
            </div>
          </div>
        </form>
      </div>

      <!-- Summary Cards -->
      <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
          <div class="stat-card">
            <div class="stat-card-content">
              <div class="stat-icon blue">
                <i class="fa fa-calendar-alt"></i>
              </div>
              <div class="stat-info">
                <div class="stat-label">Total Schedules</div>
                <div class="stat-value"><?= number_format($totalSchedules) ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6">
          <div class="stat-card">
            <div class="stat-card-content">
              <div class="stat-icon success">
                <i class="fa fa-check-circle"></i>
              </div>
              <div class="stat-info">
                <div class="stat-label">Approved</div>
                <div class="stat-value"><?= number_format($approvedCount) ?></div>
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
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?= number_format($pendingCount) ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6">
          <div class="stat-card">
            <div class="stat-card-content">
              <div class="stat-icon danger">
                <i class="fa fa-times-circle"></i>
              </div>
              <div class="stat-info">
                <div class="stat-label">Rejected</div>
                <div class="stat-value"><?= number_format($rejectedCount) ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Schedule Cards -->
      <?php if (!empty($schedules)): ?>
      <div class="row g-4">
        <?php foreach ($schedules as $sch):
          $status = $sch['admin_confirmation'] ?? 'Pending';
          $statusLower = strtolower($status);
          $badgeClass = $statusLower;
        ?>
        <div class="col-xl-4 col-lg-6">
          <div class="schedule-card <?= $statusLower ?>">
            <div class="schedule-header">
              <div class="schedule-id">#<?= $sch['Schedule_ID'] ?></div>
              <span class="status-badge <?= $badgeClass ?>"><?= $status ?></span>
            </div>

            <div class="schedule-info">
              <div class="info-row">
                <i class="fas fa-user"></i>
                <div>
                  <strong>Customer:</strong><br>
                  <?= htmlspecialchars($sch['customer_name']) ?>
                </div>
              </div>

              <div class="info-row">
                <i class="fas fa-phone"></i>
                <div>
                  <strong>Contact:</strong><br>
                  <?= htmlspecialchars($sch['contact_number']) ?>
                </div>
              </div>

              <div class="info-row">
                <i class="fas fa-envelope"></i>
                <div>
                  <strong>Email:</strong><br>
                  <?= htmlspecialchars($sch['email']) ?>
                </div>
              </div>

              <div class="info-row">
                <i class="fas fa-calendar-day"></i>
                <div>
                  <strong>Date:</strong> <?= date('M d, Y', strtotime($sch['date'])) ?>
                </div>
              </div>

              <div class="info-row">
                <i class="fas fa-clock"></i>
                <div>
                  <strong>Time:</strong> <?= date('h:i A', strtotime($sch['time'])) ?>
                </div>
              </div>

              <div class="info-row">
                <i class="fas fa-box"></i>
                <div>
                  <strong>Service:</strong><br>
                  <?= htmlspecialchars($sch['service']) ?>
                </div>
              </div>

              <div class="info-row">
                <i class="fas fa-plus-circle"></i>
                <div>
                  <strong>Add-ons:</strong> <?= htmlspecialchars($sch['add_ons'] ?: 'None') ?>
                </div>
              </div>

              <div class="info-row">
                <i class="fas fa-truck"></i>
                <div>
                  <strong>Type:</strong> <?= htmlspecialchars($sch['pick_deliver']) ?>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="schedule-actions">
              <?php if ($status === 'Pending'): ?>
              <form method="POST" class="flex-fill schedule-confirm-form">
                <input type="hidden" name="schedule_id" value="<?= $sch['Schedule_ID'] ?>">
                <button type="button"
                        data-action-label="Approve"
                        name="confirm"
                        value="Approved"
                        class="btn-schedule btn-approve w-100 js-approve-reject">
                  <i class="fas fa-check"></i> Approve
                </button>
              </form>
              <form method="POST" class="flex-fill schedule-confirm-form">
                <input type="hidden" name="schedule_id" value="<?= $sch['Schedule_ID'] ?>">
                <button type="button"
                        data-action-label="Reject"
                        name="confirm"
                        value="Rejected"
                        class="btn-schedule btn-reject w-100 js-approve-reject">
                  <i class="fas fa-times"></i> Reject
                </button>
              </form>
              <?php endif; ?>

              <button class="btn-schedule btn-edit flex-fill"
                      data-bs-toggle="modal"
                      data-bs-target="#updateModal<?= $sch['Schedule_ID'] ?>">
                <i class="fas fa-edit"></i> Edit
              </button>

              <form method="POST" class="flex-fill" id="deleteForm<?= $sch['Schedule_ID'] ?>">
                <input type="hidden" name="schedule_id" value="<?= $sch['Schedule_ID'] ?>">
                <input type="hidden" name="delete" value="1">
                <button type="button"
                        class="btn-schedule btn-delete w-100 js-delete"
                        data-delete-form="#deleteForm<?= $sch['Schedule_ID'] ?>">
                  <i class="fas fa-trash-alt"></i> Delete
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- Update Modal -->
        <div class="modal fade" id="updateModal<?= $sch['Schedule_ID'] ?>" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Update Schedule #<?= $sch['Schedule_ID'] ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <form method="POST">
                <div class="modal-body">
                  <input type="hidden" name="schedule_id" value="<?= $sch['Schedule_ID'] ?>">

                  <div class="mb-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?= $sch['date'] ?>" required>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Time</label>
                    <input type="time" name="time" class="form-control" value="<?= $sch['time'] ?>" required>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Service</label>
                    <input type="text" name="service" class="form-control" value="<?= htmlspecialchars($sch['service']) ?>" required>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Add-ons</label>
                    <input type="text" name="add_ons" class="form-control" value="<?= htmlspecialchars($sch['add_ons']) ?>" placeholder="Optional">
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Type</label>
                    <select name="pick_deliver" class="form-select" required>
                      <option value="Pickup" <?= $sch['pick_deliver'] === 'Pickup' ? 'selected' : '' ?>>Pickup</option>
                      <option value="Delivery" <?= $sch['pick_deliver'] === 'Delivery' ? 'selected' : '' ?>>Delivery</option>
                    </select>
                  </div>
                </div>

                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" name="update" class="btn" style="background: var(--dark-blue); color: white;">
                    Save Changes
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="card" style="background: white; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid rgba(168,232,249,0.2);">
        <div class="card-body text-center py-5">
          <i class="fa fa-calendar-times" style="font-size: 4rem; color: var(--light-blue); margin-bottom: 1rem;"></i>
          <h5 class="text-muted">No schedules found</h5>
          <p class="text-muted">Customer schedules will appear here once created</p>
        </div>
      </div>
      <?php endif; ?>

      <footer>
        <p class="mb-0">© <?= date('Y') ?> <strong>MangTV Laundry Shop</strong> - All Rights Reserved</p>
      </footer>
    </div>
  </main>

  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content modal-confirm">
        <div class="modal-header">
          <h5 class="modal-title">Confirm Deletion</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          Are you sure you want to delete this schedule? This action cannot be undone.
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <form id="deleteConfirmForm" method="post" class="d-inline">
            <input type="hidden" name="_method" value="delete">
            <button type="submit" class="btn btn-delete">
              Delete
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Success Modal -->
  <div class="modal fade success-modal" id="successModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body">
          <div class="success-icon <?= $successType ?>" id="successIcon">
            <i class="fa fa-check-circle"></i>
          </div>
          <h5 class="success-title" id="successTitle">Success!</h5>
          <p class="success-message" id="successMessage">
            <?= htmlspecialchars($successMessage) ?>
          </p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-success-ok" data-bs-dismiss="modal">
            <i class="fa fa-check"></i>OK
          </button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Sidebar toggle
    const toggleBtn = document.getElementById('toggleSidebar');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    if (toggleBtn) {
      toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
      });
    }

    // ========== NOTIFICATION SYSTEM ==========
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const notificationList = document.getElementById('notificationList');
    const notifBadge = document.getElementById('notifBadge');
    const markAllReadBtn = document.getElementById('markAllReadBtn');

    let currentFilter = 'all';
    let isDropdownOpen = false;

    if (notificationBtn) {
      notificationBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        isDropdownOpen = !isDropdownOpen;
        notificationDropdown.classList.toggle('show', isDropdownOpen);
        if (isDropdownOpen) {
          loadNotifications();
        }
      });
    }

    document.addEventListener('click', (e) => {
      if (!notificationDropdown.contains(e.target) && !notificationBtn.contains(e.target)) {
        isDropdownOpen = false;
        notificationDropdown.classList.remove('show');
      }
    });

    document.querySelectorAll('.notification-tab').forEach(tab => {
      tab.addEventListener('click', (e) => {
        document.querySelectorAll('.notification-tab').forEach(t => t.classList.remove('active'));
        e.target.classList.add('active');
        currentFilter = e.target.dataset.filter;
        loadNotifications();
      });
    });

    function loadNotifications() {
  const unreadOnly = currentFilter === 'unread' ? '1' : '0';
  fetch('get_notifications_admin.php?action=get_notifications&unread_only=' + unreadOnly + '&limit=15')
    .then(response => response.json())
    .then(data => {
      displayNotifications(data.notifications);
      updateBadge(data.unread_count);
    })
    .catch(() => {
      notificationList.innerHTML = '<div class="text-center text-danger py-3">Error loading notifications</div>';
    });
}


    function displayNotifications(notifications) {
      if (!notifications || notifications.length === 0) {
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

    function handleNotificationClick(notificationId, link) {
  fetch('get_notifications_admin.php?action=mark_read', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'notification_id=' + encodeURIComponent(notificationId)
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      updateBadge(data.unread_count);
    }
    if (link && link !== '#' && link !== '') {
      window.location.href = link;
    }
  })
  .catch(err => {
    console.error('Mark read error', err);
    if (link && link !== '#' && link !== '') {
      window.location.href = link;
    }
  });
}


    if (markAllReadBtn) {
      markAllReadBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        fetch('get_notifications_admin.php?action=mark_all_read', { method: 'POST' })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              updateBadge(0);
              loadNotifications();
            }
          })
          .catch(() => {});
      });
    }

    function updateBadge(count) {
      if (!notifBadge) return;
      if (count > 0) {
        notifBadge.textContent = count;
        notifBadge.style.display = 'flex';
      } else {
        notifBadge.style.display = 'none';
      }
    }

    function pollNotifications() {
      fetch('get_notifications_admin.php?action=get_count')
        .then(response => response.json())
        .then(data => { updateBadge(data.count); })
        .catch(() => {});
    }
    setInterval(pollNotifications, 30000);

    // Filter toggle
    function toggleStatusFilter() {
      const box = document.getElementById('statusFilter');
      const text = document.getElementById('filterBtnText');
      if (box.style.display === 'none') {
        box.style.display = 'block';
        if (text) text.textContent = 'Hide Filters';
      } else {
        box.style.display = 'none';
        if (text) text.textContent = 'Show Filters';
      }
    }
    <?php if (!empty($filterStatus)): ?>
    window.addEventListener('DOMContentLoaded', function() { toggleStatusFilter(); });
    <?php endif; ?>
    </script>

    <script>
    // Delete confirmation modal logic
    const osDeleteModalEl = document.getElementById('deleteConfirmModal');
    const osDeleteForm = document.getElementById('deleteConfirmForm');
    const osDeleteModal = (typeof bootstrap !== 'undefined' && osDeleteModalEl) ? new bootstrap.Modal(osDeleteModalEl) : null;
    let osDeleteTarget = { type: 'form', href: null, form: null };

    function osHandleDeleteTrigger(e) {
      const trigger = e.currentTarget;
      const formSelector = trigger.getAttribute('data-delete-form');
      const href = trigger.getAttribute('data-delete-url') || trigger.getAttribute('href');
      e.preventDefault();

      if (formSelector) {
        const form = document.querySelector(formSelector);
        if (form) {
          osDeleteTarget = { type: 'form', href: null, form };
        }
      } else if (href) {
        osDeleteTarget = { type: 'link', href, form: null };
      }

      if (!osDeleteModal || !osDeleteForm) return;

      osDeleteForm.onsubmit = function(ev) {
        ev.preventDefault();
        if (osDeleteTarget.type === 'form' && osDeleteTarget.form) {
          osDeleteTarget.form.submit();
        } else if (osDeleteTarget.href) {
          window.location.href = osDeleteTarget.href;
        }
        osDeleteModal.hide();
      };

      osDeleteModal.show();
    }

    function osBindDeleteTriggers() {
      const triggers = document.querySelectorAll('[data-confirm="delete"], .js-delete, a[data-delete-url], button[data-delete-url]');
      triggers.forEach(el => {
        if (!el.dataset.deleteBound) {
          el.addEventListener('click', osHandleDeleteTrigger);
          el.dataset.deleteBound = '1';
        }
      });
    }

    osBindDeleteTriggers();
    document.addEventListener('DOMContentLoaded', osBindDeleteTriggers);
  </script>

  <script>
    // Success Modal Auto-Show
    <?php if (!empty($successMessage)): ?>
    document.addEventListener('DOMContentLoaded', function() {
      const successModal = new bootstrap.Modal(document.getElementById('successModal'));
      
      // Set icon based on type
      const iconMap = {
        'approve': 'fa-check-circle',
        'reject': 'fa-times-circle',
        'update': 'fa-edit',
        'delete': 'fa-trash-alt'
      };
      
      const successIcon = document.getElementById('successIcon');
      const icon = successIcon.querySelector('i');
      
      if (icon) {
        icon.className = 'fa ' + (iconMap['<?= $successType ?>'] || 'fa-check-circle');
      }
      
      successModal.show();
    });
    <?php endif; ?>
  </script>
</body>
</html>
<?php $conn->close(); ?>