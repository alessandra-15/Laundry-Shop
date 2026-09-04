<?php
session_start();
include 'db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

// Logged-in user info
$customerId   = isset($_SESSION['customer_id']) ? (int) $_SESSION['customer_id'] : null;
$firstName    = $_SESSION['first_name'] ?? '';
$lastName     = $_SESSION['last_name'] ?? '';
$fullName     = trim($firstName . ' ' . $lastName);
$email        = $_SESSION['email'] ?? '';

// Initialize bookings and stats
$totalBookings = 0;
$recentBookings = [];
$activeLaundry = 0;
$completedLaundry = 0;
$totalSpent = 0;

// Total bookings
if ($customerId !== null && $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM booking_online WHERE customer_id = ?")) {
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $totalBookings = (int)$row['cnt'];
    }
    $stmt->close();
}

// Recent bookings - Get distinct bookings with latest payment status
if ($customerId !== null && $stmt = $conn->prepare("SELECT DISTINCT b.id, b.service, b.delivery_option, b.dropoff_date, b.dropoff_time,
    b.addons, b.`timestamp`, b.status, b.payment_status, b.total_amount,
    COALESCE(latest_payment.payment_status, 'Unpaid') as payment_status
    FROM booking_online b
    LEFT JOIN (
        SELECT booking_id, payment_status
        FROM payments_online
        WHERE (booking_id, payment_date) IN (
            SELECT booking_id, MAX(payment_date)
            FROM payments_online
            GROUP BY booking_id
        )
    ) latest_payment ON b.id = latest_payment.booking_id
    WHERE b.customer_id = ?
    ORDER BY b.`timestamp` DESC LIMIT 5")) {
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $recentBookings[] = $row;
    }
    $stmt->close();
}

// Active laundry count
if ($customerId !== null && $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM booking_online WHERE customer_id = ? AND status IN ('Pending', 'Processing', 'Ready')")) {
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $activeLaundry = (int)$row['cnt'];
    }
    $stmt->close();
}

// Completed laundry count
if ($customerId !== null && $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM booking_online WHERE customer_id = ? AND completed_at IS NOT NULL")) {
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $completedLaundry = (int)$row['cnt'];
    }
    $stmt->close();
}

// Total spent
$totalSpent = 0.0;
if ($customerId !== null && $stmt = $conn->prepare("SELECT COALESCE(SUM(p.amount),0) AS total_spent 
    FROM payments_online p 
    JOIN booking_online b ON p.booking_id = b.id 
    WHERE b.customer_id = ? AND p.payment_status = 'Paid'")) {
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $totalSpent = (float)$row['total_spent'];
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - MangTV Laundry Shop</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        font-size: 1.5rem;
        font-weight: bold;
        color: var(--yellow);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
    }
    
    .brand-text p {
        margin: 0;
        font-size: 0.75rem;
        opacity: 0.8;
    }
    
    .sidebar-nav {
        padding: 1.5rem 0;
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
    }
    
    .btn-primary-custom {
        background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
        color: white;
        border: none;
        padding: 0.5rem 1.25rem;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .btn-primary-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0,83,122,0.3);
        color: white;
    }
    
    .btn-yellow-custom {
        background: linear-gradient(135deg, var(--yellow) 0%, #ffe082 100%);
        color: var(--dark-blue);
        border: none;
        padding: 0.5rem 1.25rem;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .btn-yellow-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(255,213,91,0.3);
        color: var(--dark-blue);
    }
    
    .user-profile {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0,83,122,0.2);
        transition: all 0.3s;
        font-size: 0.9rem;
    }
    
    .user-profile:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 16px rgba(0,83,122,0.3);
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
    
    .stat-icon.yellow { 
        background: linear-gradient(135deg, var(--yellow) 0%, #ffe082 100%);
        color: var(--dark-blue);
    }
    
    .stat-icon.success { 
        background: linear-gradient(135deg, #198754 0%, #20c997 100%);
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
    
    /* Notification Dropdown */
    .notification-dropdown {
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        border: 1px solid rgba(168,232,249,0.3);
        border-radius: 16px;
        padding: 0;
        overflow: hidden;
        max-height: 500px;
        overflow-y: auto;
        min-width: 380px;
        animation: slideDown 0.3s ease-out;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .notification-dropdown::-webkit-scrollbar {
        width: 6px;
    }

    .notification-dropdown::-webkit-scrollbar-track {
        background: rgba(168,232,249,0.1);
    }

    .notification-dropdown::-webkit-scrollbar-thumb {
        background: rgba(0,83,122,0.3);
        border-radius: 3px;
    }

    .dropdown-header {
        position: sticky;
        top: 0;
        z-index: 10;
        background: white;
        border-bottom: 1px solid rgba(168,232,249,0.3);
    }

    .dropdown-footer {
        position: sticky;
        bottom: 0;
        background: white;
        z-index: 10;
        border-top: 1px solid rgba(168,232,249,0.3);
    }

    .notification-item {
        padding: 1rem;
        border-bottom: 1px solid rgba(168,232,249,0.2);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        text-decoration: none;
        display: block;
        color: inherit;
        position: relative;
        animation: fadeInUp 0.4s ease-out;
    }

    .notification-item:nth-child(1) { animation-delay: 0.1s; }
    .notification-item:nth-child(2) { animation-delay: 0.2s; }
    .notification-item:nth-child(3) { animation-delay: 0.3s; }
    .notification-item:nth-child(4) { animation-delay: 0.4s; }
    .notification-item:nth-child(5) { animation-delay: 0.5s; }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .notification-item:last-child {
        border-bottom: none;
    }

    .notification-item:hover {
        background: rgba(168,232,249,0.15);
        text-decoration: none;
        color: inherit;
        transform: translateX(3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .notification-item.unread {
        background: linear-gradient(90deg, rgba(255,243,91,0.15) 0%, rgba(255,243,91,0.05) 100%);
        border-left: 4px solid var(--yellow);
        animation: pulseUnread 2s infinite;
    }

    @keyframes pulseUnread {
        0%, 100% {
            box-shadow: 0 0 0 0 rgba(255,211,91, 0.4);
        }
        50% {
            box-shadow: 0 0 0 4px rgba(255,211,91, 0);
        }
    }

    .notification-item.unread::before {
        content: '';
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        width: 8px;
        height: 8px;
        background: var(--yellow);
        border-radius: 50%;
        box-shadow: 0 0 8px var(--yellow);
        animation: pulseDot 1.5s infinite;
    }

    @keyframes pulseDot {
        0%, 100% {
            transform: translateY(-50%) scale(1);
            opacity: 1;
        }
        50% {
            transform: translateY(-50%) scale(1.2);
            opacity: 0.7;
        }
    }

    .notification-item.reading {
        opacity: 0.6;
        transform: scale(0.98);
    }

    /* Toast Notifications */
    .notification-toast {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        max-width: 400px;
        padding: 0;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        transform: translateX(400px);
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
    }

    .notification-toast.show {
        transform: translateX(0);
        opacity: 1;
    }

    .toast-content {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem 1.25rem;
        color: white;
        font-weight: 500;
    }

    .toast-success {
        background: linear-gradient(135deg, #198754 0%, #20c997 100%);
    }

    .toast-error {
        background: linear-gradient(135deg, #dc3545 0%, #ff6b6b 100%);
    }

    .toast-warning {
        background: linear-gradient(135deg, #fd7e14 0%, #ff922b 100%);
    }

    .toast-info {
        background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
    }

    .toast-content i {
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    /* Bell Ring Animation */
    @keyframes bellRing {
        0%, 100% { transform: rotate(0deg); }
        10%, 30%, 50%, 70%, 90% { transform: rotate(-10deg); }
        20%, 40%, 60%, 80% { transform: rotate(10deg); }
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
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .notification-icon.booking { 
        background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%); 
        color: white; 
    }
    .notification-icon.status { 
        background: linear-gradient(135deg, #198754 0%, #20c997 100%); 
        color: white; 
    }
    .notification-icon.payment { 
        background: linear-gradient(135deg, var(--yellow) 0%, #ffe082 100%); 
        color: var(--dark-blue); 
    }
    .notification-icon.promo { 
        background: linear-gradient(135deg, #dc3545 0%, #ff6b6b 100%); 
        color: white; 
    }
    
    .notification-content h6 {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--dark-blue);
        margin-bottom: 0.25rem;
        line-height: 1.3;
    }
    
    .notification-content p {
        font-size: 0.8rem;
        color: #6c757d;
        margin: 0;
        line-height: 1.5;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .notification-time {
        font-size: 0.7rem;
        color: #adb5bd;
        margin-top: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    
    .notification-time i {
        font-size: 0.65rem;
    }
    
    .empty-notifications {
        padding: 3rem 2rem;
        text-align: center;
    }
    
    .empty-notifications i {
        font-size: 3rem;
        color: rgba(168,232,249,0.5);
        margin-bottom: 1rem;
    }
    
    .empty-notifications p {
        color: #6c757d;
        font-size: 0.9rem;
        margin: 0;
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
        .sidebar .nav-text {
            opacity: 0;
            visibility: hidden;
        }
        
        main { 
            margin-left: var(--sidebar-collapsed);
        }
        
        .notification-dropdown {
            min-width: 320px;
        }
    }
    
    @media (max-width:768px) {
        .topbar {
            padding: 1rem;
        }
        
        .stat-card {
            margin-bottom: 1rem;
        }
        
        .topbar-actions {
            gap: 0.5rem;
        }
        
        .btn-primary-custom,
        .btn-yellow-custom {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
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
                <p>Welcome, <?php echo htmlspecialchars($firstName); ?>!</p>
            </div>
        </div>
        
        <div class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="userdashboard.php" class="nav-link active">
                        <i class="fas fa-home"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="booking.php" class="nav-link">
                        <i class="fas fa-plus"></i>
                        <span class="nav-text">New Booking</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="tracking.php" class="nav-link">
                        <i class="fas fa-map-marker-alt"></i>
                        <span class="nav-text">Track Laundry</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="notifications_user.php" class="nav-link">
                        <i class="fas fa-bell"></i>
                        <span class="nav-text">Notifications</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="feedback.php" class="nav-link">
                        <i class="fas fa-star"></i>
                        <span class="nav-text">Feedback</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link">
                        <i class="fas fa-user"></i>
                        <span class="nav-text">Profile</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout_confirm.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
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
                        <h5>Hello, <?php echo htmlspecialchars($firstName); ?> 👋</h5>
                        <small>Track your laundry and manage your services</small>
                    </div>
                </div>
                <div class="topbar-actions">
                    <div class="dropdown">
                        <button class="topbar-icon" id="notifBtn" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-bell"></i>
                            <span class="badge bg-danger" id="notifCount" style="display: none;">0</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end notification-dropdown" id="notificationList">
                            <!-- Notifications will be loaded here -->
                        </ul>
                    </div>
                    <a href="booking.php" class="btn-primary-custom">
                        <i class="fas fa-plus"></i> New Booking
                    </a>
                    <a href="tracking.php" class="btn-yellow-custom">
                        <i class="fas fa-map-marker-alt"></i> Track
                    </a>
                    <div class="user-profile"><?php echo strtoupper(substr($firstName,0,1) . substr($lastName,0,1)); ?></div>
                </div>
            </div>
        </div>

        <div class="container-fluid py-4 px-4">
            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-xl-4 col-md-6">
                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-icon blue">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-label">Active Laundry</div>
                                <div class="stat-value"><?php echo $activeLaundry; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6">
                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-icon yellow">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-label">Completed Laundry</div>
                                <div class="stat-value"><?php echo $completedLaundry; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6">
                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-icon success">
                                <i class="fas fa-peso-sign"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-label">Total Spent</div>
                                <div class="stat-value success">₱<?php echo number_format($totalSpent,2); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Bookings -->
            <div class="card-custom mb-4">
                <div class="card-header-custom">
                    <h6><i class="fas fa-clipboard-list me-2"></i>Recent Bookings</h6>
                </div>
                <div class="card-body-custom">
                    <?php if (empty($recentBookings)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3" style="opacity: 0.3;"></i>
                            <p class="text-muted mb-0">No recent bookings. Create a booking to see it here.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Services</th>
                                        <th>Delivery Option</th>
                                        <th>Drop-off Date</th>
                                        <th>Drop-off Time</th>
                                        <th>Add-ons</th>
                                        <th>Created</th>
                                        <th>Status</th>
                                        <th>Payment Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentBookings as $b): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($b['id']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($b['service'] ?: '—'); ?></td>
                                        <td><?php echo htmlspecialchars($b['delivery_option'] ?: '—'); ?></td>
                                        <td><?php echo htmlspecialchars($b['dropoff_date'] ?: '—'); ?></td>
                                        <td><?php echo htmlspecialchars($b['dropoff_time'] ?: '—'); ?></td>
                                        <td><?php echo htmlspecialchars($b['addons'] ?: '—'); ?></td>
                                        <td><?php echo htmlspecialchars($b['timestamp'] ?: '—'); ?></td>
                                        <td>
                                            <?php 
                                            $status = $b['status'] ?? 'Pending';
                                            $statusClass = '';
                                            switch($status) {
                                                case 'Ready':
                                                    $statusClass = 'bg-success';
                                                    break;
                                                case 'Processing':
                                                    $statusClass = 'bg-info';
                                                    break;
                                                case 'Pending':
                                                    $statusClass = 'bg-warning text-dark';
                                                    break;
                                                default:
                                                    $statusClass = 'bg-secondary';
                                            }
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?> badge-custom"><?php echo htmlspecialchars($status); ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $paymentStatus = $b['payment_status'] ?? 'Unpaid';
                                            $badgeClass = '';
                                            switch($paymentStatus) {
                                                case 'Paid':
                                                    $badgeClass = 'bg-success';
                                                    break;
                                                case 'Pending':
                                                    $badgeClass = 'bg-warning text-dark';
                                                    break;
                                                case 'Unpaid':
                                                    $badgeClass = 'bg-danger';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?> badge-custom"><?php echo htmlspecialchars($paymentStatus); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($paymentStatus === 'Unpaid'): ?>
                                                <a href="payment.php?booking_id=<?php echo $b['id']; ?>" class="btn btn-sm btn-primary-custom">
                                                    <i class="fas fa-credit-card"></i> Pay Now
                                                </a>
                                            <?php elseif ($paymentStatus === 'Pending'): ?>
                                                <span class="badge bg-info badge-custom">Verifying...</span>
                                            <?php else: ?>
                                                <span class="badge bg-success badge-custom"><i class="fas fa-check"></i> Paid</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payment History -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <h6><i class="fas fa-credit-card me-2"></i>Payment History</h6>
                </div>
                <div class="card-body-custom">
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Reference Number</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Proof</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $paymentStmt = $conn->prepare("SELECT p.*, b.service
                                    FROM payments_online p
                                    JOIN booking_online b ON p.booking_id = b.id
                                    WHERE b.customer_id = ?
                                    ORDER BY p.payment_date DESC");
                                $paymentStmt->bind_param("i", $customerId);
                                $paymentStmt->execute();
                                $payments = $paymentStmt->get_result();
                                
                                if ($payments->num_rows > 0):
                                    while ($payment = $payments->fetch_assoc()):
                                ?>
                                <tr>
                                    <td><strong>#<?php echo htmlspecialchars($payment['booking_id']); ?></strong></td>
                                    <td><strong>₱<?php echo number_format($payment['amount'], 2); ?></strong></td>
                                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['reference_number'] ?? ''); ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($payment['payment_date'])); ?></td>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        switch($payment['payment_status']) {
                                            case 'Paid':
                                                $statusClass = 'bg-success';
                                                break;
                                            case 'Pending':
                                                $statusClass = 'bg-warning text-dark';
                                                break;
                                            case 'Failed':
                                                $statusClass = 'bg-danger';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?> badge-custom">
                                            <?php echo htmlspecialchars($payment['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($payment['payment_proof'])): ?>
                                            <?php $ext = strtolower(pathinfo($payment['payment_proof'], PATHINFO_EXTENSION)); ?>
                                            <?php $proofUrl = 'view_proof.php?ref=' . urlencode($payment['reference_number']); ?>
                                            <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                                <a href="<?php echo $proofUrl; ?>" target="_blank">
                                                    <img src="<?php echo $proofUrl; ?>" alt="Proof" style="max-width:60px;max-height:60px;border-radius:6px;border:1px solid #ccc;">
                                                </a>
                                            <?php elseif ($ext === 'pdf'): ?>
                                                <a href="<?php echo $proofUrl; ?>" target="_blank" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-file-pdf"></i> View PDF
                                                </a>
                                            <?php else: ?>
                                                <a href="<?php echo $proofUrl; ?>" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="fas fa-inbox fa-2x text-muted mb-2" style="opacity: 0.3;"></i>
                                        <p class="text-muted mb-0">No payment records found</p>
                                    </td>
                                </tr>
                                <?php
                                endif;
                                $paymentStmt->close();
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <footer>
                <p class="mb-0">© <?= date('Y') ?> <strong>Mang TV Laundry Shop</strong> - All Rights Reserved</p>
            </footer>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle functionality
        const toggleBtn = document.getElementById('toggleSidebar');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });

        // ========== REAL-TIME NOTIFICATION SYSTEM ==========

        let eventSource = null;

        // Initialize Server-Sent Events for real-time notifications
        function initializeRealTimeNotifications() {
            if (typeof(EventSource) !== "undefined") {
                // Get user ID from PHP session (you might need to pass this from PHP)
                const userId = <?php echo json_encode($_SESSION['customer_id'] ?? 0); ?>;
                const lastTimestamp = new Date(Date.now() - 3600000).toISOString().slice(0, 19).replace('T', ' ');

                eventSource = new EventSource(`sse_notifications.php?user_id=${userId}&last_timestamp=${encodeURIComponent(lastTimestamp)}`);

                eventSource.onmessage = function(event) {
                    const data = JSON.parse(event.data);
                    console.log('SSE message received:', data);

                    if (data.type === 'notification_update') {
                        // Handle notification update with enhanced feedback
                        handleNotificationUpdate(data);
                    } else if (data.type === 'connected') {
                        console.log('SSE connected:', data.message);
                        showNotificationFeedback('Real-time notifications connected', 'success');
                    } else if (data.type === 'heartbeat') {
                        console.log('SSE heartbeat:', data.timestamp);
                    } else if (data.type === 'disconnected') {
                        console.log('SSE disconnected:', data.message);
                        showNotificationFeedback('Real-time connection lost, using manual refresh', 'warning');
                    } else if (data.error) {
                        console.error('SSE error:', data.error);
                        showNotificationFeedback('Connection error, notifications may be delayed', 'error');
                    }
                };

                eventSource.onopen = function(event) {
                    console.log('SSE connection opened');
                };

                eventSource.onerror = function(event) {
                    console.error('SSE connection error:', event);
                    eventSource.close();
                    // Fallback to polling if SSE fails
                    setInterval(loadNotifications, 10000);
                };
            } else {
                console.log('EventSource not supported, using polling');
                // Fallback for browsers that don't support SSE
                setInterval(loadNotifications, 10000);
            }
        }

        // Load notifications from database via AJAX
        function loadNotifications() {
            fetch('get_notifications.php?action=get_notifications&limit=15')
                .then(response => response.json())
                .then(data => {
                    if (data.notifications) {
                        displayNotifications(data.notifications);
                        updateNotificationCount(data.unread_count);
                    }
                })
                .catch(error => {
                    console.error('Error loading notifications:', error);
                });
        }

// Display notifications in dropdown
function displayNotifications(notifications) {
    const notificationList = document.getElementById('notificationList');

    if (notifications.length === 0) {
        notificationList.innerHTML = `
            <li class="empty-notifications">
                <i class="fa fa-bell-slash"></i>
                <p class="mb-0">No new notifications</p>
            </li>
        `;
        return;
    }

    // Add header
    let html = `
        <li class="dropdown-header d-flex justify-content-between align-items-center px-3 py-2">
            <span class="fw-bold" style="color: var(--dark-blue);">Notifications</span>
            <button onclick="markAllAsRead()" class="btn btn-sm btn-link text-decoration-none p-0" style="color: var(--dark-blue); font-size: 0.8rem;">
                Mark all read
            </button>
        </li>
    `;

    // Add notifications
    html += notifications.map((notif) => `
        <li>
            <div class="notification-item ${notif.is_read == 0 ? 'unread' : ''}" onclick="markAsRead(${notif.notification_id});">
                <div class="d-flex gap-3 align-items-start">
                    <div class="notification-icon ${notif.type}">
                        <i class="${notif.icon}"></i>
                    </div>
                    <div class="flex-grow-1 notification-content">
                        <h6>${notif.title}</h6>
                        <p>${notif.message}</p>
                        <div class="notification-time">
                            <i class="fa fa-clock"></i>
                            <span data-timestamp="${notif.created_at}">${notif.time_ago}</span>
                        </div>
                    </div>
                </div>
            </div>
        </li>
    `).join('');

    notificationList.innerHTML = html;
}

        // Update notification count badge
        function updateNotificationCount(unreadCount) {
            const badge = document.getElementById('notifCount');

            if (unreadCount > 0) {
                badge.textContent = unreadCount;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }

        // Mark single notification as read
        function markAsRead(notificationId) {
            // Add visual feedback
            const notificationItem = document.querySelector(`[onclick*="markAsRead(${notificationId})"]`);
            if (notificationItem) {
                notificationItem.classList.add('reading');
            }

            fetch('get_notifications.php?action=mark_read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'notification_id=' + notificationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateNotificationCount(data.unread_count);
                    // Reload notifications to reflect changes
                    loadNotifications();

                    // Show success feedback
                    showNotificationFeedback('Notification marked as read', 'success');
                } else {
                    // Remove reading class if failed
                    if (notificationItem) {
                        notificationItem.classList.remove('reading');
                    }
                    showNotificationFeedback('Failed to mark notification as read', 'error');
                }
            })
            .catch(error => {
                console.error('Error marking notification as read:', error);
                // Remove reading class if failed
                if (notificationItem) {
                    notificationItem.classList.remove('reading');
                }
                showNotificationFeedback('Error marking notification as read', 'error');
            });
        }

        // Mark all notifications as read
        function markAllAsRead() {
            fetch('get_notifications.php?action=mark_all_read', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateNotificationCount(data.unread_count);
                    // Reload notifications to reflect changes
                    loadNotifications();
                }
            })
            .catch(error => {
                console.error('Error marking all notifications as read:', error);
            });
        }

        // Load notifications on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadNotifications();

            // Initialize real-time notifications with SSE
            initializeRealTimeNotifications();
        });

        // ========== REAL-TIME TIME AGO UPDATES ==========

        // Function to update time ago displays
        function updateTimeAgo() {
            const timeElements = document.querySelectorAll('.notification-time span');

            timeElements.forEach(span => {
                const timestamp = span.getAttribute('data-timestamp');
                if (timestamp) {
                    const timeAgo = getTimeAgo(new Date(timestamp));
                    span.textContent = timeAgo;
                }
            });
        }

        // Function to calculate time ago
        function getTimeAgo(date) {
            const now = new Date();
            const diffInSeconds = Math.floor((now - date) / 1000);

            if (diffInSeconds < 60) {
                return 'Just now';
            } else if (diffInSeconds < 3600) {
                const minutes = Math.floor(diffInSeconds / 60);
                return minutes + ' minute' + (minutes > 1 ? 's' : '') + ' ago';
            } else if (diffInSeconds < 86400) {
                const hours = Math.floor(diffInSeconds / 3600);
                return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
            } else if (diffInSeconds < 604800) {
                const days = Math.floor(diffInSeconds / 86400);
                return days + ' day' + (days > 1 ? 's' : '') + ' ago';
            } else {
                return date.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });
            }
        }

        // Update time ago every minute
        setInterval(updateTimeAgo, 60000); // Update every 60 seconds

        // ========== END REAL-TIME TIME AGO UPDATES ==========

        // ========== END REAL-TIME NOTIFICATION SYSTEM ==========
    </script>
</body>
</html>