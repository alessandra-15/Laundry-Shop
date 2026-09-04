<?php
// employees.php — MangTV Admin (Redesigned with Dashboard consistency)
include 'db_connect.php';

// Include notification helpers if file exists
if (file_exists('notification_helpers.php')) {
    include 'notification_helpers.php';
    $unreadNotifCount = getUnreadCount($conn);
} else {
    $unreadNotifCount = 0; // Default if notification system not set up yet
}

$error_msg = '';
$success_msg = '';

// --- Add Employee ---
if (isset($_POST['add_employee'])) {
  $name = $conn->real_escape_string($_POST['name']);
  $role = $conn->real_escape_string($_POST['role']);
  $contact = $conn->real_escape_string($_POST['contact']);
  $status = $conn->real_escape_string($_POST['status'] ?? 'Active');

  if (trim($name) === '' || trim($role) === '' || trim($contact) === '') {
    $error_msg = "Please fill out all required fields.";
  } else {
    $insert = "INSERT INTO employees (name, role, contact, status) VALUES ('$name', '$role', '$contact', '$status')";
    if ($conn->query($insert)) {
      $success_msg = "Employee added successfully!";
      header("Location: employees.php?success=added");
      exit;
    } else {
      $error_msg = "Error adding employee: " . $conn->error;
    }
  }
}

// --- Edit Employee ---
if (isset($_POST['edit_employee'])) {
  $id = (int)$_POST['id'];
  $name = $conn->real_escape_string($_POST['name']);
  $role = $conn->real_escape_string($_POST['role']);
  $contact = $conn->real_escape_string($_POST['contact']);
  $status = $conn->real_escape_string($_POST['status']);

  if ($id <= 0) {
    $error_msg = "Invalid employee ID.";
  } else {
    $update = "UPDATE employees SET name='$name', role='$role', contact='$contact', status='$status' WHERE id=$id";
    if ($conn->query($update)) {
      header("Location: employees.php?success=updated");
      exit;
    } else {
      $error_msg = "Error updating employee: " . $conn->error;
    }
  }
}

// --- Delete Employee ---
if (isset($_GET['delete_id'])) {
  $id = (int)$_GET['delete_id'];
  if ($id > 0) {
    $conn->query("DELETE FROM employees WHERE id=$id");
  }
  header("Location: employees.php?success=deleted");
  exit;
}

// Success messages
if (isset($_GET['success'])) {
  switch($_GET['success']) {
    case 'added': $success_msg = "Employee added successfully!"; break;
    case 'updated': $success_msg = "Employee updated successfully!"; break;
    case 'deleted': $success_msg = "Employee deleted successfully!"; break;
  }
}

// --- Filters / Search ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build where clause
$where = "1";
if ($filter_status !== 'all') {
  $where .= " AND status = '" . $conn->real_escape_string($filter_status) . "'";
}
if ($search !== '') {
  $s = $conn->real_escape_string($search);
  $where .= " AND (name LIKE '%$s%' OR role LIKE '%$s%' OR contact LIKE '%$s%')";
}

// --- Summary counts ---
$tot = (int)($conn->query("SELECT COUNT(*) AS c FROM employees")->fetch_assoc()['c'] ?? 0);
$active = (int)($conn->query("SELECT COUNT(*) AS c FROM employees WHERE status='Active'")->fetch_assoc()['c'] ?? 0);
$inactive = (int)($conn->query("SELECT COUNT(*) AS c FROM employees WHERE status='Inactive'")->fetch_assoc()['c'] ?? 0);
$roles_count = (int)($conn->query("SELECT COUNT(DISTINCT role) AS c FROM employees")->fetch_assoc()['c'] ?? 0);

// --- Employees list ---
$q = "SELECT * FROM employees WHERE $where ORDER BY id ASC";
$empR = $conn->query($q);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>MangTV Admin — Employees</title>

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

    /* Alert Messages */
    .alert-custom {
      border-radius: 12px;
      border: none;
      padding: 1rem 1.25rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      animation: slideDown 0.3s ease-out;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .alert-custom i {
      font-size: 1.25rem;
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
    
    .stat-icon.red { 
      background: linear-gradient(135deg, #dc3545 0%, #f87171 100%);
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

    /* Search & Filter Bar */
    .filter-bar {
      background: white;
      border-radius: 16px;
      padding: 1.5rem;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
      border: 1px solid rgba(168,232,249,0.2);
      margin-bottom: 1.5rem;
    }

    .search-input {
      border: 2px solid rgba(168,232,249,0.5);
      border-radius: 10px;
      padding: 0.65rem 1rem;
      font-size: 0.9rem;
      transition: all 0.3s;
    }

    .search-input:focus {
      border-color: var(--light-blue);
      box-shadow: 0 0 0 3px rgba(168,232,249,0.2);
    }

    .filter-select {
      border: 2px solid rgba(168,232,249,0.5);
      border-radius: 10px;
      padding: 0.65rem 1rem;
      font-size: 0.9rem;
      transition: all 0.3s;
    }

    .filter-select:focus {
      border-color: var(--light-blue);
      box-shadow: 0 0 0 3px rgba(168,232,249,0.2);
    }

    .btn-filter {
      background: var(--dark-blue);
      color: white;
      border: none;
      padding: 0.65rem 1.25rem;
      border-radius: 10px;
      font-weight: 600;
      transition: all 0.3s;
    }

    .btn-filter:hover {
      background: #006b99;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,83,122,0.3);
    }

    .btn-reset {
      background: #6c757d;
      color: white;
      border: none;
      padding: 0.65rem 1.25rem;
      border-radius: 10px;
      font-weight: 600;
      transition: all 0.3s;
    }

    .btn-reset:hover {
      background: #5a6268;
      transform: translateY(-2px);
    }

    .btn-add {
      background: linear-gradient(135deg, var(--yellow) 0%, #ffe082 100%);
      color: var(--dark-blue);
      border: none;
      padding: 0.65rem 1.5rem;
      border-radius: 10px;
      font-weight: 700;
      transition: all 0.3s;
      box-shadow: 0 4px 12px rgba(255,213,91,0.3);
    }

    .btn-add:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(255,213,91,0.4);
      color: var(--dark-blue);
    }
    
    /* Card */
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
      padding: 0;
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
      text-align: center;
    }

    .table-custom thead th.text-start {
      text-align: left !important;
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
      text-align: center;
    }

    .table-custom tbody td.text-start {
      text-align: left !important;
    }

    .employee-name {
      font-weight: 600;
      color: var(--dark-blue);
    }

    .employee-id {
      color: #6c757d;
      font-weight: 500;
    }
    
    /* Badges */
    .badge-custom {
      padding: 0.45rem 0.85rem;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.75rem;
      letter-spacing: 0.5px;
    }

    /* Action Buttons */
    .btn-action {
      width: 36px;
      height: 36px;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: none;
      transition: all 0.3s;
      font-size: 0.9rem;
    }

    .btn-action:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .btn-edit {
      background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%);
      color: white;
    }

    .btn-delete {
      background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
      color: white;
    }

    /* Modal Styling */
    .modal-content {
      border-radius: 16px;
      border: none;
      box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    }

    .modal-header {
      background: linear-gradient(135deg, var(--light-blue) 0%, rgba(168,232,249,0.3) 100%);
      border-bottom: 1px solid rgba(168,232,249,0.3);
      padding: 1.25rem 1.5rem;
      border-radius: 16px 16px 0 0;
    }

    .modal-header .modal-title {
      color: var(--dark-blue);
      font-weight: 700;
      font-size: 1.2rem;
    }

    .modal-body {
      padding: 1.5rem;
    }

    .modal-footer {
      border-top: 1px solid rgba(168,232,249,0.2);
      padding: 1rem 1.5rem;
    }

    .form-label {
      font-weight: 600;
      color: var(--dark-blue);
      font-size: 0.9rem;
      margin-bottom: 0.5rem;
    }

    .form-control, .form-select {
      border: 2px solid rgba(168,232,249,0.5);
      border-radius: 10px;
      padding: 0.65rem 1rem;
      font-size: 0.9rem;
      transition: all 0.3s;
    }

    .form-control:focus, .form-select:focus {
      border-color: var(--light-blue);
      box-shadow: 0 0 0 3px rgba(168,232,249,0.2);
    }

    .btn-modal-cancel {
      background: #6c757d;
      color: white;
      border: none;
      padding: 0.65rem 1.5rem;
      border-radius: 10px;
      font-weight: 600;
      transition: all 0.3s;
    }

    .btn-modal-cancel:hover {
      background: #5a6268;
      color: white;
    }

    .btn-modal-submit {
      background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
      color: white;
      border: none;
      padding: 0.65rem 1.5rem;
      border-radius: 10px;
      font-weight: 600;
      transition: all 0.3s;
    }

    .btn-modal-submit:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(0,83,122,0.3);
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
    .modal-confirm .btn-delete-confirm {
      background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
      color: #fff;
      box-shadow: 0 6px 16px rgba(220,53,69,0.3);
    }
    .modal-confirm .btn-delete-confirm:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(220,53,69,0.4);
      color: white;
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

      .filter-bar {
        padding: 1rem;
      }

      .btn-action {
        width: 32px;
        height: 32px;
        font-size: 0.8rem;
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
            <span class="nav-text">Record</span>
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
          <a href="employees.php" class="nav-link active">
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
            <h5>Employee Management</h5>
            <small>Manage your team members and their roles</small>
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
      <!-- Success/Error Messages -->
      <?php if ($success_msg): ?>
        <div class="alert alert-success alert-custom mb-4">
          <i class="fa fa-check-circle"></i>
          <span><?= htmlspecialchars($success_msg) ?></span>
        </div>
      <?php endif; ?>

      <?php if ($error_msg): ?>
        <div class="alert alert-danger alert-custom mb-4">
          <i class="fa fa-exclamation-circle"></i>
          <span><?= htmlspecialchars($error_msg) ?></span>
        </div>
      <?php endif; ?>

      <!-- Summary Cards -->
      <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
          <div class="stat-card">
            <div class="stat-card-content">
              <div class="stat-icon blue">
                <i class="fa fa-users"></i>
              </div>
              <div class="stat-info">
                <div class="stat-label">Total Employees</div>
                <div class="stat-value"><?= number_format($tot) ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6">
          <div class="stat-card">
            <div class="stat-card-content">
              <div class="stat-icon green">
                <i class="fa fa-user-check"></i>
              </div>
              <div class="stat-info">
                <div class="stat-label">Active Employees</div>
                <div class="stat-value"><?= number_format($active) ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6">
          <div class="stat-card">
            <div class="stat-card-content">
              <div class="stat-icon red">
                <i class="fa fa-user-slash"></i>
              </div>
              <div class="stat-info">
                <div class="stat-label">Inactive Employees</div>
                <div class="stat-value"><?= number_format($inactive) ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6">
          <div class="stat-card">
            <div class="stat-card-content">
              <div class="stat-icon yellow">
                <i class="fa fa-briefcase"></i>
              </div>
              <div class="stat-info">
                <div class="stat-label">Unique Roles</div>
                <div class="stat-value"><?= number_format($roles_count) ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Search & Filter Bar -->
      <div class="filter-bar">
        <div class="row g-3 align-items-end">
          <div class="col-lg-5">
            <form method="get" class="d-flex gap-2">
              <input 
                type="search" 
                name="search" 
                class="form-control search-input" 
                placeholder="Search by name, role, or contact..." 
                value="<?= htmlspecialchars($search) ?>"
              >
              <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
              <button class="btn btn-filter" type="submit">
                <i class="fa fa-search"></i>
              </button>
            </form>
          </div>

          <div class="col-lg-3">
            <form method="get" id="statusForm">
              <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
              <select name="status" class="form-select filter-select" onchange="document.getElementById('statusForm').submit()">
                <option value="all" <?= $filter_status==='all' ? 'selected' : '' ?>>All Status</option>
                <option value="Active" <?= $filter_status==='Active' ? 'selected' : '' ?>>Active Only</option>
                <option value="Inactive" <?= $filter_status==='Inactive' ? 'selected' : '' ?>>Inactive Only</option>
              </select>
            </form>
          </div>

          <div class="col-lg-4 text-end">
            <a href="employees.php" class="btn btn-reset me-2">
              <i class="fa fa-redo me-2"></i>Reset Filters
            </a>
            <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="fa fa-plus me-2"></i>Add New Employee
            </button>
          </div>
        </div>
      </div>

      <!-- Employees Table -->
      <div class="card-custom">
        <div class="card-header-custom">
          <h6><i class="fa fa-list me-2"></i>Employee Directory</h6>
        </div>
        <div class="card-body-custom">
          <div class="table-responsive">
            <table class="table table-custom">
              <thead>
                <tr>
                  <th style="width:8%;">ID</th>
                  <th style="width:28%;" class="text-start">Name</th>
                  <th style="width:20%;">Role</th>
                  <th style="width:12%;">Status</th>
                  <th style="width:18%;">Contact</th>
                  <th style="width:14%;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($empR && $empR->num_rows > 0): ?>
                  <?php while ($emp = $empR->fetch_assoc()): ?>
                    <tr>
                      <td><span class="employee-id">#<?= htmlspecialchars($emp['id']) ?></span></td>
                      <td class="text-start">
                        <span class="employee-name"><?= htmlspecialchars($emp['name']) ?></span>
                      </td>
                      <td><?= htmlspecialchars($emp['role']) ?></td>
                      <td>
                        <?php if ($emp['status'] === 'Active'): ?>
                          <span class="badge bg-success badge-custom">
                            <i class="fa fa-check-circle me-1"></i>Active
                          </span>
                        <?php else: ?>
                          <span class="badge bg-danger badge-custom">
                            <i class="fa fa-times-circle me-1"></i>Inactive
                          </span>
                        <?php endif; ?>
                      </td>
                      <td><?= htmlspecialchars($emp['contact']) ?></td>
                      <td>
                        <div class="d-flex justify-content-center gap-2">
                          <button 
                            class="btn btn-action btn-edit" 
                            data-bs-toggle="modal" 
                            data-bs-target="#editModal"
                            data-id="<?= htmlspecialchars($emp['id']) ?>"
                            data-name="<?= htmlspecialchars($emp['name']) ?>"
                            data-role="<?= htmlspecialchars($emp['role']) ?>"
                            data-contact="<?= htmlspecialchars($emp['contact']) ?>"
                            data-status="<?= htmlspecialchars($emp['status']) ?>"
                            title="Edit Employee"
                          >
                            <i class="fa fa-edit"></i>
                          </button>

                          <button 
                            class="btn btn-action btn-delete" 
                            data-bs-toggle="modal" 
                            data-bs-target="#deleteConfirmModal"
                            data-id="<?= htmlspecialchars($emp['id']) ?>"
                            data-name="<?= htmlspecialchars($emp['name']) ?>"
                            title="Delete Employee"
                          >
                            <i class="fa fa-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                      <i class="fa fa-users fa-3x mb-3 d-block" style="opacity: 0.3;"></i>
                      <p class="mb-0">No employees found matching your criteria.</p>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
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
              Are you sure you want to delete this employee? This action cannot be undone.
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
              <a href="#" id="deleteConfirmBtn" class="btn btn-delete">
                <i class="fa fa-trash me-1"></i>Delete
              </a>
            </div>
          </div>
        </div>
      </div>

      <footer>
        <p class="mb-0">© <?= date('Y') ?> <strong>MangTV Laundry Shop</strong> - All Rights Reserved</p>
      </footer>
    </div>
  </main>

  <!-- Add Employee Modal -->
  <div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form method="post" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="fa fa-user-plus me-2"></i>Add New Employee
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">
              <i class="fa fa-user me-1"></i>Full Name *
            </label>
            <input type="text" name="name" class="form-control" placeholder="Enter employee's full name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">
              <i class="fa fa-briefcase me-1"></i>Role/Position *
            </label>
            <input type="text" name="role" class="form-control" placeholder="e.g., Laundry Attendant, Manager" required>
          </div>
          <div class="mb-3">
            <label class="form-label">
              <i class="fa fa-phone me-1"></i>Contact Number *
            </label>
            <input type="text" name="contact" class="form-control" placeholder="e.g., 09XX-XXX-XXXX" required>
          </div>
          <div class="mb-3">
            <label class="form-label">
              <i class="fa fa-toggle-on me-1"></i>Status
            </label>
            <select name="status" class="form-select">
              <option value="Active" selected>Active</option>
              <option value="Inactive">Inactive</option>
            </select>
          </div>
          <small class="text-muted">
            <i class="fa fa-info-circle me-1"></i>* Required fields
          </small>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">
            <i class="fa fa-times me-2"></i>Cancel
          </button>
          <button type="submit" name="add_employee" class="btn btn-modal-submit">
            <i class="fa fa-check me-2"></i>Add Employee
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Employee Modal -->
  <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form method="post" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="fa fa-user-edit me-2"></i>Edit Employee
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="edit-id">
          <div class="mb-3">
            <label class="form-label">
              <i class="fa fa-user me-1"></i>Full Name *
            </label>
            <input type="text" name="name" id="edit-name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">
              <i class="fa fa-briefcase me-1"></i>Role/Position *
            </label>
            <input type="text" name="role" id="edit-role" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">
              <i class="fa fa-phone me-1"></i>Contact Number *
            </label>
            <input type="text" name="contact" id="edit-contact" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">
              <i class="fa fa-toggle-on me-1"></i>Status
            </label>
            <select name="status" id="edit-status" class="form-select">
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">
            <i class="fa fa-times me-2"></i>Cancel
          </button>
          <button type="submit" name="edit_employee" class="btn btn-modal-submit">
            <i class="fa fa-save me-2"></i>Save Changes
          </button>
        </div>
      </form>
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


    // Populate edit modal with employee data
    const editModalEl = document.getElementById('editModal');
    editModalEl.addEventListener('show.bs.modal', event => {
      const btn = event.relatedTarget;
      const id = btn.getAttribute('data-id');
      const name = btn.getAttribute('data-name');
      const role = btn.getAttribute('data-role');
      const contact = btn.getAttribute('data-contact');
      const status = btn.getAttribute('data-status');

      document.getElementById('edit-id').value = id;
      document.getElementById('edit-name').value = name;
      document.getElementById('edit-role').value = role;
      document.getElementById('edit-contact').value = contact;
      document.getElementById('edit-status').value = status;
    });

    // Populate delete modal with employee data
    const deleteModalEl = document.getElementById('deleteConfirmModal');
    deleteModalEl.addEventListener('show.bs.modal', event => {
      const btn = event.relatedTarget;
      const id = btn.getAttribute('data-id');

      document.getElementById('deleteConfirmBtn').href = 'employees.php?delete_id=' + id;
    });

    // Auto-dismiss alerts after 5 seconds
    setTimeout(() => {
      const alerts = document.querySelectorAll('.alert-custom');
      alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
      });
    }, 5000);
  </script>
</body>
</html>

<?php $conn->close(); ?>