<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

$customerId = $_SESSION['customer_id'];
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $contactNumber = trim($_POST['contact_number']);
    $address = trim($_POST['address']);

    $stmt = $conn->prepare("UPDATE customer_info SET first_name = ?, last_name = ?, contact_number = ?, Address = ?, updated_at = NOW() WHERE Customer_ID = ?");
    $stmt->bind_param("ssssi", $firstName, $lastName, $contactNumber, $address, $customerId);
    
    if ($stmt->execute()) {
        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name'] = $lastName;
        $success_message = 'Profile updated successfully!';
    } else {
        $error_message = 'Failed to update profile. Please try again.';
    }
    $stmt->close();
}

$stmt = $conn->prepare("SELECT * FROM customer_info WHERE Customer_ID = ?");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$conn->close();

$firstName     = $user['first_name'] ?? '';
$lastName      = $user['last_name'] ?? '';
$fullName      = trim($firstName . ' ' . $lastName);
$email         = $user['email'] ?? '';
$contactNumber = $user['contact_number'] ?? '';
$address       = $user['Address'] ?? '';
$accountType   = $user['account_type'] ?? '—';
$registerDate  = $user['register_date'] ?? '—';
$createdAt     = $user['created_at'] ?? '—';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - MangTV Laundry Shop</title>
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
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
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
            z-index: 1000; 
            overflow-y: auto;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
        }
        
        .sidebar::-webkit-scrollbar { width: 6px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.1); }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 3px; }
        
        .sidebar.collapsed { width: var(--sidebar-collapsed); }
        .sidebar.collapsed .brand-text, .sidebar.collapsed .nav-text { opacity: 0; visibility: hidden; }
        
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
        
        .brand-logo i { font-size: 1.5rem; color: var(--dark-blue); }
        
        .brand-text { transition: all 0.3s; }
        .brand-text h4 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--yellow);
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        .brand-text p { margin: 0; font-size: 0.75rem; opacity: 0.8; }
        
        .sidebar-nav { padding: 1.5rem 0; }
        .sidebar .nav-link { 
            color: rgba(255,255,255,0.85);
            padding: 0.85rem 1.25rem; 
            display: flex;
            gap: 1rem; 
            align-items: center; 
            font-weight: 500;
            font-size: 0.95rem;
            margin: 0.25rem 0.75rem;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.3s;
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

        main { 
            margin-left: var(--sidebar-width); 
            transition: margin-left 0.3s;
            min-height: 100vh;
        }
        
        main.expanded { margin-left: var(--sidebar-collapsed); }

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
            font-size: 0.9rem;
            box-shadow: 0 4px 12px rgba(0,83,122,0.2);
            transition: all 0.3s;
        }

        .user-profile:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 16px rgba(0,83,122,0.3);
        }

        /* Profile Header Card */
        .profile-header-card {
            background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
            border-radius: 20px;
            padding: 3rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0,83,122,0.2);
            position: relative;
            overflow: hidden;
        }

        .profile-header-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(168,232,249,0.15) 0%, transparent 70%);
        }

        .profile-header-content {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--yellow);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark-blue);
            font-size: 2.5rem;
            font-weight: bold;
            border: 5px solid rgba(255,255,255,0.2);
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            flex-shrink: 0;
        }

        .profile-info {
            flex: 1;
        }

        .profile-info h3 {
            color: white;
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-size: 1.8rem;
        }

        .profile-info p {
            color: rgba(255,255,255,0.9);
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        .account-badge {
            background: var(--yellow);
            color: var(--dark-blue);
            padding: 0.5rem 1.25rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 12px rgba(255,213,91,0.3);
        }

        /* Card Styles */
        .card-custom { 
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid rgba(168,232,249,0.2);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .card-header-custom {
            background: linear-gradient(135deg, var(--light-blue) 0%, rgba(168,232,249,0.3) 100%);
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(168,232,249,0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            padding: 2rem 1.5rem;
        }

        .form-label {
            color: var(--dark-blue);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.3s;
            font-size: 0.95rem;
        }

        .form-control:focus {
            border-color: var(--yellow);
            box-shadow: 0 0 0 3px rgba(255, 213, 91, 0.2);
        }

        .form-control:disabled {
            background: #f8f9fa;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .btn-edit {
            background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
            color: white;
            border: none;
            padding: 0.65rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,83,122,0.3);
        }

        .btn-save {
            background: linear-gradient(135deg, #198754 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 0.65rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(25,135,84,0.3);
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.65rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-cancel:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .edit-controls {
            display: none;
            gap: 10px;
        }

        .edit-controls.show {
            display: flex;
        }

        /* Info Item */
        .info-item {
            padding: 1rem;
            background: var(--bg-light);
            border-radius: 12px;
            margin-bottom: 1rem;
            border-left: 4px solid var(--yellow);
            transition: all 0.3s;
        }

        .info-item:hover {
            background: rgba(168,232,249,0.1);
            transform: translateX(5px);
        }

        .info-item-label {
            color: #6c757d;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .info-item-value {
            color: var(--dark-blue);
            font-weight: 600;
            font-size: 1rem;
        }

        /* Alert Styles */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        @media (max-width:992px) {
            .sidebar { width: var(--sidebar-collapsed); }
            .sidebar .brand-text, .sidebar .nav-text { opacity: 0; visibility: hidden; }
            main { margin-left: var(--sidebar-collapsed); }
            
            .profile-header-content {
                flex-direction: column;
                text-align: center;
            }
        }

        @media (max-width:768px) {
            .profile-avatar {
                width: 100px;
                height: 100px;
                font-size: 2rem;
            }

            .edit-controls {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
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
                    <a href="userdashboard.php" class="nav-link">
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
                    <a href="feedback.php" class="nav-link">
                        <i class="fas fa-star"></i>
                        <span class="nav-text">Feedback</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link active">
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

    <main id="mainContent">
        <div class="topbar">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <button class="toggle-btn" id="toggleSidebar">
                        <i class="fa fa-bars"></i>
                    </button>
                    <div class="topbar-title">
                        <h5>My Profile</h5>
                        <small>View and manage your account information</small>
                    </div>
                </div>
                <div class="user-profile"><?php echo strtoupper(substr($firstName,0,1) . substr($lastName,0,1)); ?></div>
            </div>
        </div>

        <div class="container-fluid py-4 px-4">
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><strong>Success!</strong> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><strong>Error!</strong> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Profile Header -->
            <div class="profile-header-card">
                <div class="profile-header-content">
                    <div class="profile-avatar">
                        <?= strtoupper(substr($firstName,0,1) . substr($lastName,0,1)) ?>
                    </div>
                    <div class="profile-info">
                        <h3><?= htmlspecialchars($fullName) ?></h3>
                        <p><i class="fas fa-envelope me-2"></i><?= htmlspecialchars($email) ?></p>
                        <span class="account-badge">
                            <i class="fas fa-crown"></i>
                            <?= htmlspecialchars($accountType) ?> Account
                        </span>
                    </div>
                </div>
            </div>

            <!-- Personal Information Card -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <h6><i class="fas fa-user-circle me-2"></i>Personal Information</h6>
                    <button type="button" class="btn-edit" id="editBtn" onclick="enableEdit()">
                        <i class="fas fa-edit"></i>Edit Profile
                    </button>
                </div>
                <div class="card-body-custom">
                    <form method="POST" action="profile.php" id="profileForm">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="fas fa-user me-2" style="color: var(--yellow);"></i>First Name</label>
                                <input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($firstName) ?>" disabled required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="fas fa-user me-2" style="color: var(--yellow);"></i>Last Name</label>
                                <input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($lastName) ?>" disabled required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-envelope me-2" style="color: var(--yellow);"></i>Email Address</label>
                            <input type="email" class="form-control" value="<?= htmlspecialchars($email) ?>" disabled>
                            <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Email cannot be changed for security reasons</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-phone me-2" style="color: var(--yellow);"></i>Contact Number</label>
                            <input type="text" class="form-control" name="contact_number" value="<?= htmlspecialchars($contactNumber) ?>" disabled required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label"><i class="fas fa-map-marker-alt me-2" style="color: var(--yellow);"></i>Address</label>
                            <textarea class="form-control" name="address" rows="3" disabled required><?= htmlspecialchars($address) ?></textarea>
                        </div>

                        <div class="edit-controls" id="editControls">
                            <button type="submit" class="btn-save">
                                <i class="fas fa-save"></i>Save Changes
                            </button>
                            <button type="button" class="btn-cancel" onclick="cancelEdit()">
                                <i class="fas fa-times"></i>Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Account Details Card -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <h6><i class="fas fa-info-circle me-2"></i>Account Details</h6>
                </div>
                <div class="card-body-custom">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-item">
                                <div class="info-item-label">
                                    <i class="fas fa-id-card me-1"></i>Account Type
                                </div>
                                <div class="info-item-value"><?= htmlspecialchars($accountType) ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-item">
                                <div class="info-item-label">
                                    <i class="fas fa-calendar-plus me-1"></i>Member Since
                                </div>
                                <div class="info-item-value"><?= htmlspecialchars(date('M d, Y', strtotime($registerDate))) ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-item">
                                <div class="info-item-label">
                                    <i class="fas fa-clock me-1"></i>Last Updated
                                </div>
                                <div class="info-item-value"><?= htmlspecialchars(date('M d, Y', strtotime($createdAt))) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const toggleBtn = document.getElementById('toggleSidebar');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });

        function enableEdit() {
            document.querySelectorAll('#profileForm input:not([type="email"]), #profileForm textarea').forEach(field => {
                field.disabled = false;
            });
            document.getElementById('editBtn').style.display = 'none';
            document.getElementById('editControls').classList.add('show');
        }

        function cancelEdit() {
            document.querySelectorAll('#profileForm input, #profileForm textarea').forEach(field => {
                field.disabled = true;
            });
            document.getElementById('editBtn').style.display = 'inline-flex';
            document.getElementById('editControls').classList.remove('show');
            location.reload();
        }
    </script>
</body>
</html>