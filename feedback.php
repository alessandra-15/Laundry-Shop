<?php
session_start();
include 'db_connect.php';

// Include notification helpers if file exists
if (file_exists('notification_helpers.php')) {
    include 'notification_helpers.php';
    $unreadNotifCount = getUnreadCount($conn);
} else {
    $unreadNotifCount = 0;
}

$sql = "SELECT f.feedback_id, f.user_id, f.booking_id, f.rating, f.comment, 
               f.admin_response, f.created_at, f.responded_at,
               c.first_name, c.last_name,
               b.service AS service_name
        FROM feedback f
        JOIN customer_info c ON f.user_id = c.Customer_ID
        JOIN booking b ON f.booking_id = b.Booking_ID
        ORDER BY f.created_at DESC";

$result = $conn->query($sql);

// --- Get total feedbacks ---
$totalQuery = "SELECT COUNT(*) AS total FROM feedback";
$totalResult = $conn->query($totalQuery);
$totalFeedbacks = ($totalResult && $totalResult->num_rows > 0) ? $totalResult->fetch_assoc()['total'] : 0;

// --- Get average rating ---
$avgQuery = "SELECT AVG(rating) AS avg_rating FROM feedback";
$avgResult = $conn->query($avgQuery);
$averageRating = ($avgResult && $avgResult->num_rows > 0) ? round($avgResult->fetch_assoc()['avg_rating'], 1) : 0;

// --- Get positive reviews (rating 4 or 5) ---
$positiveQuery = "SELECT COUNT(*) AS positive FROM feedback WHERE rating >= 4";
$positiveResult = $conn->query($positiveQuery);
$positiveCount = ($positiveResult && $positiveResult->num_rows > 0) ? $positiveResult->fetch_assoc()['positive'] : 0;

// --- Compute positive percentage ---
$positivePercentage = ($totalFeedbacks > 0) ? round(($positiveCount / $totalFeedbacks) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MangTV - Feedback</title>
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
        
        .container-main {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.75rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid rgba(168,232,249,0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
        }
        
        .stat-icon.positive {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .stat-icon.neutral {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        .stat-icon.info {
            background: rgba(0, 123, 255, 0.1);
            color: #007bff;
        }
        
        .stat-value {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--dark-blue);
            line-height: 1;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        /* Cards */
        .card-custom {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid rgba(168,232,249,0.2);
            margin-bottom: 1.5rem;
        }
        
        .card-header-custom {
            background: linear-gradient(135deg, rgba(168,232,249,0.25) 0%, rgba(168,232,249,0.1) 100%);
            padding: 1.5rem 1.75rem;
            border-radius: 16px 16px 0 0;
            border-bottom: 2px solid rgba(168,232,249,0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .card-header-custom h6 {
            margin: 0;
            font-weight: 700;
            color: var(--dark-blue);
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .card-body-custom {
            padding: 1.5rem;
        }
        
        /* Feedback Card */
        .feedback-card {
            background: white;
            border-radius: 16px;
            padding: 1.75rem;
            margin-bottom: 1.25rem;
            border: 1px solid rgba(168,232,249,0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        .feedback-card:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            transform: translateY(-4px);
            border-color: var(--yellow);
        }
        
        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.25rem;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .feedback-customer {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
            min-width: 200px;
        }
        
        .customer-avatar {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--dark-blue), var(--light-blue));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.3rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0,83,122,0.2);
        }
        
        .customer-info h6 {
            margin: 0 0 0.25rem 0;
            font-weight: 600;
            color: var(--dark-blue);
            font-size: 1.05rem;
        }
        
        .customer-info small {
            color: #6c757d;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .feedback-rating {
            display: flex;
            gap: 0.3rem;
            flex-shrink: 0;
        }
        
        .star {
            font-size: 1.25rem;
            transition: transform 0.2s;
        }
        
        .star.fas {
            color: #ffc107;
        }
        
        .star.far {
            color: #e0e0e0;
        }
        
        .feedback-card:hover .star.fas {
            transform: scale(1.1);
        }
        
        .feedback-content {
            margin: 1.25rem 0;
            padding: 1.25rem;
            background: var(--bg-light);
            border-radius: 12px;
            border-left: 4px solid var(--yellow);
        }
        
        .feedback-content p {
            margin: 0;
            color: var(--text-dark);
            line-height: 1.7;
            font-size: 0.95rem;
        }
        
        .feedback-response {
            margin-top: 1rem;
            padding: 1.25rem;
            background: linear-gradient(135deg, rgba(168,232,249,0.15) 0%, rgba(168,232,249,0.05) 100%);
            border-radius: 12px;
            border-left: 4px solid var(--light-blue);
            animation: slideIn 0.3s ease-out;
        }
        
        .feedback-response strong {
            color: var(--dark-blue);
            font-size: 0.95rem;
            display: block;
            margin-bottom: 0.75rem;
        }
        
        .feedback-response p {
            margin: 0;
            color: var(--text-dark);
            line-height: 1.6;
            font-size: 0.95rem;
        }
        
        .feedback-response small {
            display: block;
            margin-top: 0.75rem;
            font-size: 0.8rem;
            color: #6c757d;
            font-style: italic;
        }
        
        .feedback-footer {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 0.75rem;
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(168,232,249,0.2);
        }
        
        /* Buttons */
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(0,83,122,0.2);
            min-width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,83,122,0.3);
            color: white;
        }
        
        .btn-success-custom {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
            min-width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-success-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            color: white;
        }
        
        .btn-secondary-custom {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-secondary-custom:hover {
            background: #5a6268;
            color: white;
            transform: translateY(-2px);
        }
        
        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .filter-tab {
            padding: 0.85rem 1.5rem;
            border-radius: 12px;
            background: white;
            border: 2px solid rgba(168,232,249,0.3);
            color: var(--text-dark);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-tab:hover {
            border-color: var(--yellow);
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(255,213,91,0.3);
        }
        
        .filter-tab.active {
            background: linear-gradient(135deg, var(--yellow) 0%, #ffd970 100%);
            border-color: var(--yellow);
            color: var(--dark-blue);
            box-shadow: 0 4px 16px rgba(255,213,91,0.4);
        }
        
        /* Form Controls */
        .form-label {
            color: var(--dark-blue);
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid rgba(168,232,249,0.5);
            padding: 0.7rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--yellow);
            box-shadow: 0 0 0 0.25rem rgba(255,213,91,0.25);
        }
        
        /* Modal */
        .modal-custom {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .modal-custom.show {
            display: flex;
        }
        
        .modal-content-custom {
            background: white;
            border-radius: 20px;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .modal-header-custom {
            background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
            color: white;
            padding: 1.75rem 2rem;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header-custom h5 {
            margin: 0;
            font-weight: 700;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s;
        }
        
        .modal-close:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .modal-body-custom {
            padding: 2rem;
        }
        
        .modal-footer-custom {
            padding: 1.5rem 2rem;
            background: var(--bg-light);
            border-radius: 0 0 20px 20px;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            border-top: 2px solid rgba(168,232,249,0.3);
        }
        
        /* Toast Notification */
        .toast-notif {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 1rem 1.75rem;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(40, 167, 69, 0.3);
            z-index: 9999;
            font-weight: 600;
            animation: slideInUp 0.3s ease-out;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            opacity: 0.2;
            color: var(--light-blue);
        }
        
        .empty-state h5 {
            color: var(--dark-blue);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        /* Animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            .container-main {
                padding: 0 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .topbar {
                padding: 1rem;
            }

            .feedback-card {
                padding: 1.25rem;
            }
            
            .customer-avatar {
                width: 45px;
                height: 45px;
                font-size: 1.1rem;
            }
            
            .feedback-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .feedback-rating {
                align-self: flex-start;
            }

            .feedback-footer {
                flex-direction: column;
                align-items: stretch;
                gap: 0.5rem;
            }
            
            .feedback-footer .btn {
                width: 100%;
                justify-content: center;
            }
            
            .filter-tabs {
                overflow-x: auto;
                flex-wrap: nowrap;
                padding-bottom: 0.5rem;
            }
            
            .filter-tab {
                flex-shrink: 0;
            }
            
            .stat-card {
                padding: 1.25rem;
            }
            
            .stat-icon {
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
            }
            
            .stat-value {
                font-size: 1.75rem;
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
                    <a href="feedback.php" class="nav-link active">
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
                        <h5>Customer Feedback</h5>
                        <small>View and manage customer reviews and feedback</small>
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

        <div class="container-main">
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon positive">
                            <i class="fas fa-star"></i>
                        </div>
                        <div>
                            <p class="stat-label mb-1">Average Rating</p>
                            <h2 class="stat-value mb-0"><?= $averageRating ?></h2>
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon info">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div>
                            <p class="stat-label mb-1">Total Feedbacks</p>
                            <h2 class="stat-value mb-0"><?= $totalFeedbacks ?></h2>
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon positive">
                            <i class="fas fa-thumbs-up"></i>
                        </div>
                        <div>
                            <p class="stat-label mb-1">Positive Reviews</p>
                            <h2 class="stat-value mb-0"><?= $positivePercentage ?>%</h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <div class="filter-tab active" onclick="filterFeedback('all')">
                    <i class="fas fa-list me-2"></i>All Feedback
                </div>
                <div class="filter-tab" onclick="filterFeedback('5')">
                    <i class="fas fa-star me-2"></i>5 Stars
                </div>
                <div class="filter-tab" onclick="filterFeedback('4')">
                    <i class="fas fa-star me-2"></i>4 Stars
                </div>
                <div class="filter-tab" onclick="filterFeedback('3')">
                    <i class="fas fa-star me-2"></i>3 Stars
                </div>
                <div class="filter-tab" onclick="filterFeedback('low')">
                    <i class="fas fa-exclamation-triangle me-2"></i>Low Ratings
                </div>
            </div>

            <!-- Feedback List -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <h6 id="cardTitle"><i class="fas fa-comments me-2"></i>All Feedback</h6>
                    <div class="d-flex gap-2">
                        <select class="form-select" style="width:auto;" onchange="sortFeedback(this.value)">
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="highest">Highest Rating</option>
                            <option value="lowest">Lowest Rating</option>
                        </select>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div id="feedbackList">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): 
                                $customer = $row['first_name'].' '.$row['last_name'];
                                $firstLetter = strtoupper($customer[0]);
                                $rating = $row['rating'];
                                $service = htmlspecialchars($row['service_name']);
                                $comment = htmlspecialchars($row['comment']);
                                $date = date('F j, Y', strtotime($row['created_at']));
                            ?>
                            <div class="feedback-card" data-feedback-id="<?= $row['feedback_id'] ?>" data-rating="<?= $rating ?>" data-date="<?= $row['created_at'] ?>">
                                <div class="feedback-header">
                                    <div class="feedback-customer">
                                        <div class="customer-avatar"><?= $firstLetter ?></div>
                                        <div class="customer-info">
                                            <h6><?= $customer ?></h6>
                                            <small><i class="fas fa-calendar me-1"></i><?= $date ?> | <?= $service ?></small>
                                        </div>
                                    </div>
                                    <div class="feedback-rating">
                                        <?php for ($i=1; $i<=5; $i++): ?>
                                            <i class="<?= $i <= $rating ? 'fas' : 'far' ?> fa-star star"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <div class="feedback-content"><p><?= $comment ?></p></div>

                                <?php if (!empty($row['admin_response'])): ?>
                                    <div class="feedback-response">
                                        <strong><i class="fas fa-reply me-2"></i>Admin Response:</strong>
                                        <p><?= htmlspecialchars($row['admin_response']) ?></p>
                                        <small class="text-muted">Replied on <?= date('F j, Y g:i A', strtotime($row['responded_at'])) ?></small>
                                    </div>
                                <?php endif; ?>

                                <div class="feedback-footer d-flex justify-content-end gap-2">
                                    <button class="btn btn-primary-custom" 
                                        onclick="viewFeedback('<?= addslashes($comment) ?>','<?= addslashes($customer) ?>','<?= $rating ?>','<?= $service ?>','<?= $date ?>')">
                                        <i class="fas fa-eye"></i>
                                    </button>

                                    <?php if (empty($row['admin_response'])): ?>
                                        <button class="btn btn-success-custom respond-btn" 
                                            onclick="openResponseModal(<?= $row['feedback_id'] ?>, '<?= addslashes($customer) ?>', '<?= addslashes($comment) ?>')">
                                            <i class="fas fa-reply"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <h5>No feedback found</h5>
                                <p>There are no feedback entries in the system.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- View Modal -->
    <div class="modal-custom" id="viewModal">
        <div class="modal-content-custom">
            <div class="modal-header-custom">
                <h5><i class="fas fa-eye me-2"></i>Feedback Details</h5>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body-custom" id="modalContent"></div>
            <div class="modal-footer-custom">
                <button class="btn btn-secondary-custom" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Response Modal -->
    <div class="modal-custom" id="responseModal">
        <div class="modal-content-custom">
            <div class="modal-header-custom">
                <h5><i class="fas fa-reply me-2"></i>Respond to Feedback</h5>
                <button class="modal-close" onclick="closeResponseModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body-custom">
                <div class="mb-3">
                    <label class="form-label">Customer:</label>
                    <p id="responseCustomerName" class="fw-bold"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label">Original Feedback:</label>
                    <div class="feedback-content">
                        <p id="responseFeedbackText"></p>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Your Response:</label>
                    <textarea class="form-control" id="responseText" rows="4" placeholder="Type your response here..."></textarea>
                </div>
            </div>
            <div class="modal-footer-custom">
                <button class="btn btn-secondary-custom" onclick="closeResponseModal()">Cancel</button>
                <button class="btn btn-primary-custom" onclick="submitResponse()">
                    <i class="fas fa-paper-plane me-2"></i>Send Response
                </button>
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


        // ========== FEEDBACK FUNCTIONS ==========
        function filterFeedback(filter) {
            document.querySelectorAll('.feedback-card').forEach(card => {
                const rating = parseInt(card.dataset.rating);
                if (filter === 'all') {
                    card.style.display = 'block';
                } else if (filter === 'low' && rating <= 2) {
                    card.style.display = 'block';
                } else if (rating == parseInt(filter)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
            
            document.querySelectorAll('.filter-tab').forEach(tab => tab.classList.remove('active'));
            event.target.closest('.filter-tab').classList.add('active');
            
            const titles = {
                'all': 'All Feedback',
                '5': '5 Star Feedback',
                '4': '4 Star Feedback',
                '3': '3 Star Feedback',
                'low': 'Low Rating Feedback'
            };
            document.getElementById('cardTitle').innerHTML = `<i class="fas fa-comments me-2"></i>${titles[filter] || 'All Feedback'}`;
        }

        function sortFeedback(sortType) {
            const list = document.getElementById('feedbackList');
            const cards = Array.from(list.querySelectorAll('.feedback-card'));
            
            cards.sort((a, b) => {
                if (sortType === 'highest') {
                    return parseInt(b.dataset.rating) - parseInt(a.dataset.rating);
                } else if (sortType === 'lowest') {
                    return parseInt(a.dataset.rating) - parseInt(b.dataset.rating);
                } else if (sortType === 'oldest') {
                    return new Date(a.dataset.date) - new Date(b.dataset.date);
                } else {
                    return new Date(b.dataset.date) - new Date(a.dataset.date);
                }
            });
            
            list.innerHTML = '';
            cards.forEach(card => list.appendChild(card));
        }

        function viewFeedback(feedback, customer, rating, service, date) {
            const stars = Array.from({length:5}, (_,i) => 
                `<i class='${i<rating ? "fas" : "far"} fa-star star'></i>`).join('');
            
            document.getElementById('modalContent').innerHTML = `
                <div class='mb-3'>
                    <h5>${customer}</h5>
                    <div class='feedback-rating mb-2'>${stars}</div>
                    <small><i class='fas fa-calendar me-1'></i>${date} | ${service}</small>
                </div>
                <div class='feedback-content'><p>${feedback}</p></div>
            `;
            document.getElementById('viewModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('viewModal').classList.remove('show');
        }

        function openResponseModal(feedbackId, customer, feedback) {
            document.getElementById('responseCustomerName').textContent = customer;
            document.getElementById('responseFeedbackText').textContent = feedback;
            document.getElementById('responseText').value = '';
            document.getElementById('responseText').dataset.feedbackId = feedbackId;
            document.getElementById('responseModal').classList.add('show');
        }

        function closeResponseModal() {
            document.getElementById('responseModal').classList.remove('show');
        }

        function submitResponse() {
            const responseText = document.getElementById('responseText');
            const feedbackId = parseInt(responseText.dataset.feedbackId);
            const response = responseText.value.trim();

            if (!response) {
                alert('Please enter a response.');
                return;
            }

            fetch('respond_feedback.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `feedback_id=${feedbackId}&response=${encodeURIComponent(response)}`
            })
            .then(res => res.text())
            .then(data => {
                if (data === 'success') {
                    const card = document.querySelector(`.feedback-card[data-feedback-id='${feedbackId}']`);
                    
                    if (card) {
                        const responseDiv = document.createElement('div');
                        responseDiv.classList.add('feedback-response');
                        responseDiv.innerHTML = `
                            <strong><i class="fas fa-reply me-2"></i>Admin Response:</strong>
                            <p>${response}</p>
                            <small class="text-muted">Replied just now</small>
                        `;
                        
                        const footer = card.querySelector('.feedback-footer');
                        footer.parentNode.insertBefore(responseDiv, footer);

                        const btn = card.querySelector('.respond-btn');
                        if (btn) btn.remove();
                    }

                    showNotification('Response submitted successfully!');
                    closeResponseModal();
                } else {
                    alert('Error: ' + data);
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred. Please check console.');
            });
        }

        function showNotification(message) {
            const notif = document.createElement('div');
            notif.className = 'toast-notif';
            notif.textContent = message;
            document.body.appendChild(notif);
            setTimeout(() => notif.remove(), 3000);
        }

        window.onclick = function(event) {
            const viewModal = document.getElementById('viewModal');
            const responseModal = document.getElementById('responseModal');
            
            if (event.target === viewModal) {
                closeModal();
            }
            if (event.target === responseModal) {
                closeResponseModal();
            }
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>