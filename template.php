<?php
// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in (except for login and register pages)
$current_page = basename($_SERVER['PHP_SELF']);
$public_pages = ['login.php', 'register.php', 'homepage.php'];

if (!isset($_SESSION['customer_id']) && !in_array($current_page, $public_pages)) {
    header("Location: login.php");
    exit();
}

// Get user info if logged in
$firstName = $_SESSION['first_name'] ?? '';
$lastName = $_SESSION['last_name'] ?? '';
$fullName = trim($firstName . ' ' . $lastName);
$email = $_SESSION['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Mang TV Laundry Shop'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --light-blue: #A8E8F9;
            --dark-teal: #00537A;
            --pale-yellow: #FFD35B;
            --gradient-primary: linear-gradient(135deg, #00537A 0%, #006B96 100%);
            --gradient-accent: linear-gradient(135deg, #FFD35B 0%, #FFC933 100%);
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.12);
            --shadow-lg: 0 8px 32px rgba(0,0,0,0.16);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: var(--gradient-primary);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: var(--shadow-lg);
        }

        .sidebar-header {
            padding: 30px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.15);
            background: rgba(0,0,0,0.1);
        }

        .sidebar-header h4 {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-header h4 i {
            font-size: 1.5rem;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        .sidebar-header p {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
        }

        .nav-menu {
            padding: 25px 0;
        }

        .nav-item {
            padding: 16px 25px;
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            margin: 4px 15px;
            border-radius: 12px;
        }

        .nav-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 0;
            background: var(--pale-yellow);
            border-radius: 0 4px 4px 0;
            transition: height 0.3s ease;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
        }

        .nav-item:hover::before {
            height: 60%;
        }

        .nav-item.active {
            background: var(--pale-yellow);
            color: var(--dark-teal);
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(255, 211, 91, 0.3);
        }

        .nav-item.active::before {
            display: none;
        }

        .nav-item i {
            width: 24px;
            font-size: 20px;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 35px;
        }

        /* Top Bar */
        .top-bar {
            background: white;
            padding: 25px 35px;
            border-radius: 20px;
            margin-bottom: 35px;
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .top-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-accent);
        }

        .welcome-text h2 {
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 1.8rem;
        }

        .welcome-text p {
            color: #666;
            margin: 0;
            font-size: 0.95rem;
        }

        .user-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn-new-booking {
            background: var(--gradient-accent);
            color: var(--dark-teal);
            border: none;
            padding: 12px 28px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none !important;
        }

        .btn-new-booking:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 211, 91, 0.4);
        }

        .btn-new-booking i {
            transition: transform 0.3s ease;
        }

        .btn-new-booking:hover i {
            transform: rotate(90deg);
        }

        .user-profile {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }

        .user-profile:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-md);
        }

        /* Common Card Styles */
        .section-card {
            background: white;
            padding: 35px;
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            margin-bottom: 35px;
            transition: all 0.3s ease;
        }

        /* Form Styles */
        .form-control, .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 15px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--pale-yellow);
            box-shadow: 0 0 0 3px rgba(255, 211, 91, 0.2);
        }

        .form-label {
            color: var(--dark-teal);
            font-weight: 600;
            margin-bottom: 8px;
        }

        /* Button Styles */
        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            padding: 12px 28px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        /* Table Styles */
        .table {
            margin-bottom: 0;
        }

        .table th {
            background: var(--gradient-primary);
            color: white;
            font-weight: 600;
            border: none;
        }

        .table td {
            vertical-align: middle;
            color: #444;
        }

        /* Additional styles can be added in child pages */
        <?php if (isset($additional_styles)) echo $additional_styles; ?>
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-tshirt"></i> Mang TV Laundry</h4>
            <p>Welcome<?php echo isset($firstName) ? ", {$firstName}!" : "!"; ?></p>
        </div>
        <div class="nav-menu">
            <a class="nav-item <?php echo $current_page === 'userdashboard.php' ? 'active' : ''; ?>" href="userdashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a class="nav-item <?php echo $current_page === 'booking.php' ? 'active' : ''; ?>" href="booking.php">
                <i class="fas fa-plus"></i>
                <span>New Booking</span>
            </a>
            <a class="nav-item <?php echo $current_page === 'tracking.php' ? 'active' : ''; ?>" href="tracking.php">
                <i class="fas fa-map-marker-alt"></i>
                <span>Track Laundry</span>
            </a>
            <a class="nav-item <?php echo $current_page === 'feedback.php' ? 'active' : ''; ?>" href="feedback.php">
                <i class="fas fa-star"></i>
                <span>Feedback</span>
            </a>
            <a class="nav-item <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>" href="profile.php">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a class="nav-item <?php echo $current_page === 'logout_confirm.php' ? 'active' : ''; ?>" href="logout_confirm.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php if (!in_array($current_page, $public_pages)): ?>
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="welcome-text">
                <h2><?php echo $page_header ?? 'Hello, ' . htmlspecialchars($firstName) . ' 👋'; ?></h2>
                <p><?php echo $page_description ?? 'Track your laundry and manage your services'; ?></p>
            </div>
            <div class="user-actions">
                <?php if ($current_page !== 'booking.php'): ?>
                <a href="booking.php" class="btn-new-booking">
                    <i class="fas fa-plus"></i> New Booking
                </a>
                <?php endif; ?>
                <?php if ($current_page !== 'tracking.php'): ?>
                <a href="tracking.php" class="btn-new-booking" style="background: var(--gradient-primary); color: #fff;">
                    <i class="fas fa-map-marker-alt"></i> Track Laundry
                </a>
                <?php endif; ?>
                <div class="user-profile">
                    <?php echo isset($firstName, $lastName) ? strtoupper(substr($firstName,0,1) . substr($lastName,0,1)) : '?'; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Page Content -->
        <?php if (isset($content)) echo $content; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (isset($additional_scripts)) echo $additional_scripts; ?>
</body>
</html>