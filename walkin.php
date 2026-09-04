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

$searchResults = [];
$selectedCustomer = null;

// --- ADD CUSTOMER ---
if (isset($_POST['add_customer'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $contact_number = '+63' . trim($_POST['contact_number']);
    $address = trim($_POST['address']);
    $password = password_hash("defaultpassword", PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO customer_info (first_name, last_name, email, contact_number, Address, password)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $first_name, $last_name, $email, $contact_number, $address, $password);

    if ($stmt->execute()) {
        $selectedCustomer = [
            'Customer_ID' => $conn->insert_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'contact_number' => $contact_number,
            'Address' => $address,
            'email' => $email
        ];

        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                selectedCustomer = {
                    id: '{$conn->insert_id}',
                    name: '" . addslashes($first_name . ' ' . $last_name) . "',
                    phone: '" . addslashes($contact_number) . "',
                    address: '" . addslashes($address) . "',
                    email: '" . addslashes($email) . "',
                    totalBookings: 0
                };
                showNotification('Customer added successfully!');
                displaySelectedCustomer();
                loadCustomerHistory();
                goToStep(2);
            });
        </script>";
    }
}

// --- SEARCH CUSTOMER ---
if (isset($_POST['search'])) {
    $searchTerm = trim($_POST['searchCustomer']);
    $sql = "SELECT Customer_ID, first_name, last_name, contact_number, Address, email 
            FROM customer_info 
            WHERE first_name LIKE ? OR last_name LIKE ? OR contact_number LIKE ?";
    $stmt = $conn->prepare($sql);
    $like = "%$searchTerm%";
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $stmt2 = $conn->prepare("SELECT COUNT(*) as totalBookings FROM booking WHERE Customer_ID = ?");
        $stmt2->bind_param("i", $row['Customer_ID']);
        $stmt2->execute();
        $bookingRes = $stmt2->get_result()->fetch_assoc();
        $row['totalBookings'] = $bookingRes['totalBookings'] ?? 0;
        $searchResults[] = $row;
    }
}

// --- FETCH SERVICES ---
$services = [];
$sql_services = "SELECT * FROM services ORDER BY service_name ASC";
$result_services = $conn->query($sql_services);
if ($result_services && $result_services->num_rows > 0) {
    while ($row = $result_services->fetch_assoc()) {
        $services[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MangTV - Walk-in</title>
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
        
        /* Progress Steps */
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 30px;
            left: 0;
            right: 0;
            height: 4px;
            background: #e0e0e0;
            z-index: 0;
        }
        
        .progress-line {
            position: absolute;
            top: 30px;
            left: 0;
            height: 4px;
            background: var(--yellow);
            transition: width 0.3s;
            z-index: 1;
        }
        
        .step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 2;
        }
        
        .step-circle {
            width: 60px;
            height: 60px;
            background: white;
            border: 4px solid #e0e0e0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.75rem;
            font-size: 1.5rem;
            color: #999;
            transition: all 0.3s;
        }
        
        .step.active .step-circle {
            border-color: var(--yellow);
            background: var(--yellow);
            color: var(--dark-blue);
        }
        
        .step.completed .step-circle {
            border-color: var(--dark-blue);
            background: var(--dark-blue);
            color: white;
        }
        
        .step-title {
            font-weight: 600;
            color: #999;
            font-size: 0.9rem;
        }
        
        .step.active .step-title {
            color: var(--dark-blue);
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
            background: linear-gradient(135deg, var(--light-blue) 0%, rgba(168,232,249,0.3) 100%);
            padding: 1.25rem 1.5rem;
            border-radius: 16px 16px 0 0;
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
        
        /* Customer Card */
        .customer-card {
            background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .customer-card h5 {
            margin: 0 0 1rem 0;
            font-weight: 700;
        }
        
        .customer-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .info-item i {
            font-size: 1.2rem;
            opacity: 0.8;
        }
        
        /* Schedule Grid */
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .schedule-slot {
            background: white;
            border: 3px solid #e0e0e0;
            border-radius: 12px;
            padding: 1.25rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .schedule-slot:hover {
            border-color: var(--yellow);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .schedule-slot.selected {
            border-color: var(--dark-blue);
            background: var(--light-blue);
        }
        
        .schedule-slot.unavailable {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f5f5f5;
        }
        
        .schedule-slot.unavailable:hover {
            transform: none;
            border-color: #e0e0e0;
        }
        
        .slot-time {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark-blue);
            margin-bottom: 0.5rem;
        }
        
        .slot-status {
            font-size: 0.8rem;
            color: #666;
        }
        
        /* Service Selection */
        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 1rem;
        }
        
        .service-btn {
            background: white;
            border: 3px solid #e0e0e0;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .service-btn:hover {
            border-color: var(--yellow);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .service-btn.selected {
            border-color: var(--dark-blue);
            background: var(--light-blue);
        }
        
        .service-btn i {
            font-size: 2rem;
            color: var(--dark-blue);
            margin-bottom: 0.75rem;
        }
        
        .service-name {
            font-weight: 700;
            color: var(--dark-blue);
            margin-bottom: 0.25rem;
        }
        
        .service-price {
            color: #666;
            font-size: 0.85rem;
        }
        
        /* Buttons */
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,83,122,0.3);
        }
        
        .btn-secondary-custom {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-secondary-custom:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        /* Summary Box */
        .summary-box {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            position: sticky;
            top: 20px;
        }
        
        .summary-box h5 {
            color: var(--dark-blue);
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .summary-total {
            display: flex;
            justify-content: space-between;
            padding: 1rem 0;
            margin-top: 1rem;
            border-top: 3px solid var(--dark-blue);
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--dark-blue);
        }
        
        .step-content {
            display: none;
        }
        
        .step-content.active {
            display: block;
        }
        
        /* History Item */
        .history-item {
            background: var(--bg-light);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            border: 1px solid rgba(168,232,249,0.3);
        }
        
        .history-item:hover {
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .badge-custom {
            padding: 0.35rem 0.75rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
        }
        
        /* Notification Modal */
        .notification-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .notification-modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        
        .notification-modal {
            background: white;
            border-radius: 16px;
            padding: 2rem 2rem 1.5rem;
            width: 340px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            transform: scale(0.9);
            transition: transform 0.3s ease;
            text-align: center;
            position: relative;
        }
        
        .notification-modal-overlay.show .notification-modal {
            transform: scale(1);
        }
        
        .notification-modal .icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            position: relative;
        }
        
        .notification-modal.success .icon {
            background: transparent;
        }
        
        .notification-modal.success .icon svg {
            width: 70px;
            height: 70px;
        }
        
        .notification-modal.success .icon .checkmark-circle {
            stroke: #10b981;
            stroke-width: 3;
            fill: none;
            stroke-dasharray: 220;
            stroke-dashoffset: 220;
            animation: drawCircle 0.6s ease-out forwards;
        }
        
        .notification-modal.success .icon .checkmark {
            stroke: #10b981;
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: drawCheck 0.4s 0.6s ease-out forwards;
        }
        
        @keyframes drawCircle {
            to {
                stroke-dashoffset: 0;
            }
        }
        
        @keyframes drawCheck {
            to {
                stroke-dashoffset: 0;
            }
        }
        
        .notification-modal.error .icon {
            background: transparent;
        }
        
        .notification-modal.error .icon svg {
            width: 70px;
            height: 70px;
        }
        
        .notification-modal.error .icon .error-circle {
            stroke: #ef4444;
            stroke-width: 3;
            fill: none;
            stroke-dasharray: 220;
            stroke-dashoffset: 220;
            animation: drawCircle 0.6s ease-out forwards;
        }
        
        .notification-modal.error .icon .error-x {
            stroke: #ef4444;
            stroke-width: 3;
            stroke-linecap: round;
            fill: none;
            stroke-dasharray: 60;
            stroke-dashoffset: 60;
            animation: drawCheck 0.4s 0.6s ease-out forwards;
        }
        
        .notification-modal h4 {
            color: var(--dark-blue);
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }
        
        .notification-modal p {
            color: #6b7280;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .notification-modal .btn-close-modal {
            background: #10b981;
            color: white;
            border: none;
            padding: 0.65rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }
        
        .notification-modal.error .btn-close-modal {
            background: #ef4444;
        }
        
        .notification-modal .btn-close-modal:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }
        
        .notification-modal.error .btn-close-modal:hover {
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }
        
        .notification-modal .btn-close-modal:active {
            transform: translateY(0);
        }
        
        .notification-modal .close-icon {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
            color: #999;
        }
        
        .notification-modal .close-icon:hover {
            background: rgba(0, 0, 0, 0.1);
            transform: rotate(90deg);
            color: #333;
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
            
            .notification-modal {
                min-width: 90vw;
                max-width: 90vw;
                padding: 1.5rem;
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
                    <a href="walkin.php" class="nav-link active">
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
                        <h5>Walk-in Customer Booking</h5>
                        <small>Register customer, select schedule & services</small>
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
            <!-- Progress Steps -->
            <div class="progress-steps">
                <div class="progress-line" id="progressLine"></div>
                
                <div class="step active" id="step1">
                    <div class="step-circle">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="step-title">Customer Info</div>
                </div>
                
                <div class="step" id="step2">
                    <div class="step-circle">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="step-title">Schedule & Services</div>
                </div>
                
                <div class="step" id="step3">
                    <div class="step-circle">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="step-title">Confirmation</div>
                </div>
            </div>

            <!-- STEP 1: Customer Information -->
            <div class="step-content active" id="content1">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h6><i class="fas fa-search me-2"></i>Search Customer</h6>
                    </div>
                    <div class="card-body-custom">
                        <form method="POST" id="searchForm">
                            <div class="mb-3">
                                <label class="form-label">Search by Phone or Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="searchCustomer" name="searchCustomer" placeholder="Enter phone number or name..." required>
                                    <button class="btn btn-primary-custom" type="submit" name="search">
                                        <i class="fas fa-search me-2"></i>Search
                                    </button>
                                </div>
                            </div>
                        </form>

                        <div id="searchResults">
                            <?php if (!empty($searchResults)): ?>
                                <h6 class="text-muted mb-3">Search Results:</h6>
                                <?php foreach ($searchResults as $cust): ?>
                                    <div class="history-item" style="cursor:pointer;" 
                                         onclick="selectExistingCustomer({
                                            id: <?= $cust['Customer_ID'] ?>,
                                            name: '<?= addslashes($cust['first_name'].' '.$cust['last_name']) ?>',
                                            phone: '<?= addslashes($cust['contact_number']) ?>',
                                            address: '<?= addslashes($cust['Address']) ?>',
                                            email: '<?= addslashes($cust['email']) ?>',
                                            totalBookings: <?= $cust['totalBookings'] ?>
                                         }, event)">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?= $cust['first_name'].' '.$cust['last_name'] ?></h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-phone me-1"></i><?= $cust['contact_number'] ?><br>
                                                    <i class="fas fa-map-marker-alt me-1"></i><?= $cust['Address'] ?>
                                                </small>
                                            </div>
                                            <span class="badge-custom bg-primary"><?= $cust['totalBookings'] ?> bookings</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php elseif (isset($_POST['search'])): ?>
                                <div class="alert alert-info">No customers found. Please register as new customer below.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card-custom" id="registrationCard">
                    <div class="card-header-custom">
                        <h6><i class="fas fa-user-plus me-2"></i>New Customer Registration</h6>
                    </div>
                    <div class="card-body-custom">
                        <form method="POST" id="registerForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name *</label>
                                    <input type="text" class="form-control" name="first_name" placeholder="Juan" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" name="last_name" placeholder="Dela Cruz" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">+63</span>
                                        <input type="tel" class="form-control" id="custPhone" name="contact_number" placeholder="9xxxxxxxxx" maxlength="10" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Address *</label>
                                <textarea class="form-control" id="custAddress" name="address" rows="2" placeholder="Complete address" required></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email (Optional)</label>
                                    <input type="email" class="form-control" id="custEmail" name="email" placeholder="email@example.com">
                                </div>
                            </div>

                            <button type="submit" name="add_customer" class="btn btn-primary-custom">
                                <i class="fas fa-arrow-right me-2"></i>Register & Continue
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- STEP 2: Schedule & Services (Combined) -->
            <div class="step-content" id="content2">
                <div class="customer-card" id="selectedCustomer"></div>

                <div class="row">
                    <div class="col-lg-8">
                        <!-- Schedule Selection -->
                        <div class="card-custom">
                            <div class="card-header-custom">
                                <h6><i class="fas fa-calendar me-2"></i>Select Schedule</h6>
                            </div>
                            <div class="card-body-custom">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Date</label>
                                        <input type="date" class="form-control" id="scheduleDate" onchange="loadAvailableSlots()">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Time Slot</label>
                                        <div class="schedule-grid" id="timeSlots">
                                            <p class="text-muted">Please select a date first</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Services Selection -->
                        <div class="card-custom">
                            <div class="card-header-custom">
                                <h6><i class="fas fa-tshirt me-2"></i>Select Services</h6>
                            </div>
                            <div class="card-body-custom">
                                <div class="service-grid">
                                    <?php foreach ($services as $service): ?>
                                        <div class="service-btn" onclick="toggleService('service-<?= $service['service_id'] ?>', <?= $service['price_fixed'] ?>, '<?= addslashes($service['service_name']) ?>')">
                                            <i class="fas fa-check-circle"></i>
                                            <div class="service-name"><?= $service['service_name'] ?></div>
                                            <?php if(!empty($service['description'])): ?>
                                                <div class="service-desc" style="font-size: 0.75rem; color: #666; margin: 0.5rem 0;"><?= $service['description'] ?></div>
                                            <?php endif; ?>
                                            <div class="service-price">₱<?= number_format($service['price_fixed'],2) ?></div>
                                            <?php if(!empty($service['extra_fee'])): ?>
                                                <div class="service-note" style="font-size: 0.7rem; color: #999; margin-top: 0.25rem;">
                                                    Extra Fee: ₱<?= number_format($service['extra_fee'],2) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <h6 class="text-muted mb-3 mt-4">Add-ons (Optional)</h6>
                                <div class="row">
                                    <div class="col-md-12 mb-2">
                                        <div class="form-check" style="background: var(--light-blue); padding: 1rem; border-radius: 10px;">
                                            <input class="form-check-input" type="checkbox" id="addon-extra-dry" onchange="toggleAddon('extra-dry', 15)">
                                            <label class="form-check-label fw-bold" for="addon-extra-dry">
                                                Extra Dry (+₱15)
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-12 mb-2">
                                        <div class="form-check" style="background: var(--light-blue); padding: 1rem; border-radius: 10px;">
                                            <input class="form-check-input" type="checkbox" id="addon-detergent" onchange="toggleAddon('liquid-detergent', 10)">
                                            <label class="form-check-label fw-bold" for="addon-detergent">
                                                Liquid Detergent per Cup (+₱10)
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-12 mb-2">
                                        <div class="form-check" style="background: var(--light-blue); padding: 1rem; border-radius: 10px;">
                                            <input class="form-check-input" type="checkbox" id="addon-conditioner" onchange="toggleAddon('fabric-conditioner', 10)">
                                            <label class="form-check-label fw-bold" for="addon-conditioner">
                                                Fabric Conditioner (+₱10)
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Service Type & Instructions -->
                        <div class="card-custom">
                            <div class="card-header-custom">
                                <h6><i class="fas fa-cog me-2"></i>Additional Options</h6>
                            </div>
                            <div class="card-body-custom">
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-truck me-2"></i>Service Type</label>
                                    <select class="form-select" id="pickupDelivery" onchange="updateTotal()">
                                        <option value="walkin">Walk-in (Customer will pickup at shop)</option>
                                        <option value="delivery">Delivery - ₱20</option>
                                    </select>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label"><i class="fas fa-comment me-2"></i>Special Instructions</label>
                                    <textarea class="form-control" id="instructions" rows="3" placeholder="Any special requests or notes..."></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button class="btn btn-secondary-custom" onclick="goToStep(1)">
                                <i class="fas fa-arrow-left me-2"></i>Back
                            </button>
                            <button class="btn btn-primary-custom" onclick="proceedToConfirmation()">
                                <i class="fas fa-arrow-right me-2"></i>Review Booking
                            </button>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="summary-box">
                            <h5><i class="fas fa-receipt me-2"></i>Booking Summary</h5>
                            <div id="summaryDetails">
                                <p class="text-muted">No services selected</p>
                            </div>
                        </div>

                        <div class="card-custom mt-3">
                            <div class="card-header-custom">
                                <h6><i class="fas fa-history me-2"></i>Customer History</h6>
                            </div>
                            <div class="card-body-custom">
                                <div id="customerHistory">
                                    <p class="text-muted small">Loading history...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 3: Confirmation -->
            <div class="step-content" id="content3">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card-custom">
                            <div class="card-header-custom">
                                <h6><i class="fas fa-check-circle me-2"></i>Review Your Booking</h6>
                            </div>
                            <div class="card-body-custom">
                                <!-- Customer Info -->
                                <div class="mb-4">
                                    <h6 class="text-dark mb-3"><i class="fas fa-user me-2"></i>Customer Information</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="p-3" style="background: var(--bg-light); border-radius: 10px;">
                                                <small class="text-muted d-block mb-1">Name</small>
                                                <strong id="confirm-customer-name"></strong>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-3" style="background: var(--bg-light); border-radius: 10px;">
                                                <small class="text-muted d-block mb-1">Phone</small>
                                                <strong id="confirm-customer-phone"></strong>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="p-3" style="background: var(--bg-light); border-radius: 10px;">
                                                <small class="text-muted d-block mb-1">Address</small>
                                                <strong id="confirm-customer-address"></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Schedule Info -->
                                <div class="mb-4">
                                    <h6 class="text-dark mb-3"><i class="fas fa-calendar-check me-2"></i>Schedule</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="p-3" style="background: var(--bg-light); border-radius: 10px;">
                                                <small class="text-muted d-block mb-1">Date</small>
                                                <strong id="confirm-schedule-date"></strong>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-3" style="background: var(--bg-light); border-radius: 10px;">
                                                <small class="text-muted d-block mb-1">Time</small>
                                                <strong id="confirm-schedule-time"></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Services -->
                                <div class="mb-4">
                                    <h6 class="text-dark mb-3"><i class="fas fa-list me-2"></i>Selected Services</h6>
                                    <div id="confirm-services-list"></div>
                                </div>

                                <!-- Service Type & Instructions -->
                                <div class="mb-4">
                                    <h6 class="text-dark mb-3"><i class="fas fa-info-circle me-2"></i>Additional Details</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="p-3" style="background: var(--bg-light); border-radius: 10px;">
                                                <small class="text-muted d-block mb-1">Service Type</small>
                                                <strong id="confirm-service-type"></strong>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="p-3" style="background: var(--bg-light); border-radius: 10px;">
                                                <small class="text-muted d-block mb-1">Special Instructions</small>
                                                <strong id="confirm-instructions"></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Total Amount -->
                                <div class="p-4" style="background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%); border-radius: 12px;">
                                    <div class="d-flex justify-content-between align-items-center text-white">
                                        <h5 class="mb-0">TOTAL AMOUNT</h5>
                                        <h3 class="mb-0" id="confirm-total-amount">₱0.00</h3>
                                    </div>
                                </div>

                                <div class="d-flex gap-2 mt-4">
                                    <button class="btn btn-secondary-custom" onclick="goToStep(2)">
                                        <i class="fas fa-arrow-left me-2"></i>Back to Edit
                                    </button>
                                    <button class="btn btn-primary-custom flex-grow-1" onclick="confirmBooking()">
                                        <i class="fas fa-check me-2"></i>Confirm & Save Booking
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Notification Modal -->
    <div class="notification-modal-overlay" id="notificationModal">
        <div class="notification-modal">
            <div class="close-icon" onclick="closeNotificationModal()">
                <i class="fas fa-times"></i>
            </div>
            <div class="icon" id="modalIcon">
                <!-- SVG checkmark will be inserted here for success -->
            </div>
            <h4 id="modalTitle">Success!</h4>
            <p id="modalMessage">Your action was completed successfully.</p>
            <button class="btn-close-modal" onclick="closeNotificationModal()">
                <span>Got it!</span>
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ========== SIDEBAR TOGGLE ==========
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('mainContent').classList.toggle('expanded');
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

        // ========== BOOKING SYSTEM ==========
        // Global variables
        let currentStep = 1;
        let selectedCustomer = null;
        let selectedSchedule = null;
        let selectedServices = {};
        let totalAmount = 0;

        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('scheduleDate').min = today;

        // STEP 1: Customer Search & Registration
        function selectExistingCustomer(customer, event) {
            if (event) event.preventDefault();

            selectedCustomer = customer;

            document.querySelectorAll('#searchResults .history-item').forEach(el => el.classList.remove('selected'));
            if (event && event.currentTarget) event.currentTarget.classList.add('selected');

            displaySelectedCustomer();
            loadCustomerHistory();

            goToStep(2);
        }

        // Step Navigation
        function goToStep(step) {
            if (step === 2 && !selectedCustomer) {
                showNotification('Please select or register a customer first', true);
                return;
            }

            currentStep = step;
            document.querySelectorAll('.step').forEach((el, index) => {
                if (index + 1 < step) {
                    el.classList.add('completed');
                    el.classList.remove('active');
                } else if (index + 1 === step) {
                    el.classList.add('active');
                    el.classList.remove('completed');
                } else {
                    el.classList.remove('active', 'completed');
                }
            });

            const lineWidth = ((step - 1) / 2) * 100;
            document.getElementById('progressLine').style.width = lineWidth + '%';

            document.querySelectorAll('.step-content').forEach((el, index) => {
                el.classList.toggle('active', index + 1 === step);
            });

            if (step === 2) {
                displaySelectedCustomer();
                loadCustomerHistory();
            } else if (step === 3) {
                displayConfirmation();
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function displaySelectedCustomer() {
            const html = `
                <h5><i class="fas fa-user me-2"></i>Selected Customer</h5>
                <div class="customer-info">
                    <div class="info-item">
                        <i class="fas fa-user"></i>
                        <span>${selectedCustomer.name}</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-phone"></i>
                        <span>${selectedCustomer.phone}</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>${selectedCustomer.address}</span>
                    </div>
                    ${selectedCustomer.email ? `
                        <div class="info-item">
                            <i class="fas fa-envelope"></i>
                            <span>${selectedCustomer.email}</span>
                        </div>
                    ` : ''}
                </div>
            `;
            document.getElementById('selectedCustomer').innerHTML = html;
        }

        function loadCustomerHistory() {
            const mockHistory = [
                { date: '2025-10-15', service: 'Wash, Dry & Fold', status: 'Completed', amount: 280 },
                { date: '2025-09-20', service: 'Wash Only', status: 'Completed', amount: 150 },
                { date: '2025-08-10', service: 'Wash, Dry & Fold', status: 'Completed', amount: 320 }
            ];

            let html = '';
            if (mockHistory.length === 0) {
                html = '<p class="text-muted small">No previous bookings</p>';
            } else {
                mockHistory.forEach(booking => {
                    const statusClass = booking.status === 'Completed' ? 'bg-success' : 'bg-warning';
                    html += `
                        <div class="history-item">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <small class="fw-bold">${booking.date}</small>
                                <span class="badge-custom ${statusClass}">${booking.status}</span>
                            </div>
                            <small class="text-muted d-block">${booking.service}</small>
                            <small class="fw-bold text-success">₱${booking.amount}</small>
                        </div>
                    `;
                });
            }
            document.getElementById('customerHistory').innerHTML = html;
        }

        function loadAvailableSlots() {
            const date = document.getElementById('scheduleDate').value;
            if (!date) return;

            const slots = [
                { time: '08:00 AM', available: true },
                { time: '09:00 AM', available: true },
                { time: '10:00 AM', available: false },
                { time: '11:00 AM', available: true },
                { time: '01:00 PM', available: true },
                { time: '02:00 PM', available: false },
                { time: '03:00 PM', available: true },
                { time: '04:00 PM', available: true }
            ];

            let html = '';
            slots.forEach(slot => {
                const unavailableClass = slot.available ? '' : 'unavailable';
                const statusText = slot.available ? 'Available' : 'Booked';
                html += `
                    <div class="schedule-slot ${unavailableClass}" onclick="selectTimeSlot('${slot.time}', ${slot.available}, event)">
                        <div class="slot-time">${slot.time}</div>
                        <div class="slot-status">${statusText}</div>
                    </div>
                `;
            });

            document.getElementById('timeSlots').innerHTML = html;
        }

        function selectTimeSlot(time, available, event) {
            if (!available) {
                showNotification('This time slot is booked.', true);
                return;
            }

            document.querySelectorAll('.schedule-slot').forEach(el => el.classList.remove('selected'));
            event.currentTarget.classList.add('selected');

            selectedSchedule = { date: document.getElementById('scheduleDate').value, time };
        }

        function proceedToConfirmation() {
            if (!selectedSchedule) {
                showNotification('Please select a date and time slot', true);
                return;
            }

            if (Object.keys(selectedServices).length === 0) {
                showNotification('Please select at least one service', true);
                return;
            }

            goToStep(3);
        }

        // STEP 3: Confirmation
        function displayConfirmation() {
            document.getElementById('confirm-customer-name').textContent = selectedCustomer.name;
            document.getElementById('confirm-customer-phone').textContent = selectedCustomer.phone;
            document.getElementById('confirm-customer-address').textContent = selectedCustomer.address;

            const scheduleDate = new Date(selectedSchedule.date);
            const formattedDate = scheduleDate.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            document.getElementById('confirm-schedule-date').textContent = formattedDate;
            document.getElementById('confirm-schedule-time').textContent = selectedSchedule.time;

            let servicesHTML = '';
            for (let serviceId in selectedServices) {
                const service = selectedServices[serviceId];
                servicesHTML += `
                    <div class="d-flex justify-content-between align-items-center p-3 mb-2" style="background: var(--bg-light); border-radius: 10px;">
                        <span>${service.name}</span>
                        <strong class="text-primary">₱${service.price}</strong>
                    </div>
                `;
            }
            document.getElementById('confirm-services-list').innerHTML = servicesHTML;

            const serviceType = document.getElementById('pickupDelivery').value;
            const serviceTypeText = serviceType === 'delivery' ? 'Delivery (+₱20)' : 'Walk-in (Customer Pickup)';
            document.getElementById('confirm-service-type').textContent = serviceTypeText;

            const instructions = document.getElementById('instructions').value.trim();
            document.getElementById('confirm-instructions').textContent = instructions || 'None';

            document.getElementById('confirm-total-amount').textContent = '₱' + totalAmount.toFixed(2);
        }

        function toggleService(serviceId, price, name) {
            const btn = event.currentTarget;
            
            if (selectedServices[serviceId]) {
                delete selectedServices[serviceId];
                btn.classList.remove('selected');
            } else {
                selectedServices[serviceId] = {
                    name: name,
                    price: price
                };
                btn.classList.add('selected');
            }

            updateTotal();
        }

        function toggleAddon(addonId, price) {
            const checkbox = event.currentTarget;
            const addonName = checkbox.nextElementSibling.textContent.trim();
            
            if (checkbox.checked) {
                selectedServices['addon-' + addonId] = {
                    name: addonName,
                    price: price
                };
            } else {
                delete selectedServices['addon-' + addonId];
            }

            updateTotal();
        }

        function updateTotal() {
            let subtotal = 0;
            let html = '';

            for (let serviceId in selectedServices) {
                const service = selectedServices[serviceId];
                subtotal += service.price;
                html += `
                    <div class="summary-item">
                        <span>${service.name}</span>
                        <span>₱${service.price}</span>
                    </div>
                `;
            }

            const serviceType = document.getElementById('pickupDelivery')?.value || 'walkin';
            let deliveryCost = 0;
            if (serviceType === 'delivery') {
                deliveryCost = 20;
                html += `
                    <div class="summary-item">
                        <span>Delivery</span>
                        <span>₱20</span>
                    </div>
                `;
            }

            totalAmount = subtotal + deliveryCost;

            if (Object.keys(selectedServices).length === 0 && deliveryCost === 0) {
                document.getElementById('summaryDetails').innerHTML = '<p class="text-muted">No services selected</p>';
            } else {
                html += `
                    <div class="summary-total">
                        <span>TOTAL</span>
                        <span>₱${totalAmount}</span>
                    </div>
                `;
                document.getElementById('summaryDetails').innerHTML = html;
            }
        }

        function confirmBooking() {
            if (Object.keys(selectedServices).length === 0) {
                showNotification('Please select at least one service', true);
                return;
            }

            let servicesList = [];
            let addOnsList = [];

            for (let id in selectedServices) {
                if (id.startsWith('addon-')) {
                    addOnsList.push(selectedServices[id].name);
                } else {
                    servicesList.push(selectedServices[id].name);
                }
            }

            const pickupDelivery = document.getElementById('pickupDelivery').value;
            const instructions = document.getElementById('instructions').value;

            const formData = new FormData();
            formData.append('Customer_ID', selectedCustomer.id);
            formData.append('schedule_date', selectedSchedule.date);
            formData.append('schedule_time', selectedSchedule.time);
            formData.append('service', servicesList.join(', '));
            formData.append('add_ons', addOnsList.join(', '));
            formData.append('pick_deliver', pickupDelivery);
            formData.append('special_instructions', instructions);
            formData.append('status', 'Pending');
            formData.append('total_amount', totalAmount);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'save_booking.php', true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    const response = xhr.responseText;
                    if (response === 'success') {
                        showNotification('Booking saved successfully!');
                        setTimeout(() => resetBooking(), 2000);
                    } else {
                        showNotification('❌ Error: ' + response, true);
                    }
                } else {
                    showNotification('❌ Server error', true);
                }
            };
            xhr.send(formData);
        }

        function resetBooking() {
            currentStep = 1;
            selectedCustomer = null;
            selectedSchedule = null;
            selectedServices = {};
            totalAmount = 0;

            document.getElementById('searchCustomer').value = '';
            document.getElementById('scheduleDate').value = '';
            document.getElementById('instructions').value = '';
            document.getElementById('pickupDelivery').value = 'walkin';
            document.getElementById('searchResults').innerHTML = '';
            document.getElementById('timeSlots').innerHTML = '<p class="text-muted">Please select a date first</p>';
            document.getElementById('addon-extra-dry').checked = false;
            document.getElementById('addon-detergent').checked = false;
            document.getElementById('addon-conditioner').checked = false;

            document.querySelectorAll('.service-btn.selected').forEach(el => el.classList.remove('selected'));

            goToStep(1);
        }

        function showNotification(message, isError = false) {
            const modal = document.getElementById('notificationModal');
            const modalContainer = modal.querySelector('.notification-modal');
            const iconDiv = document.getElementById('modalIcon');
            const title = document.getElementById('modalTitle');
            const messageEl = document.getElementById('modalMessage');

            // Set content
            messageEl.textContent = message;

            if (isError) {
                modalContainer.classList.remove('success');
                modalContainer.classList.add('error');
                
                // Create animated SVG error icon (circle with X)
                iconDiv.innerHTML = `
                    <svg viewBox="0 0 52 52">
                        <circle class="error-circle" cx="26" cy="26" r="25"/>
                        <path class="error-x" d="M16 16 L36 36 M36 16 L16 36"/>
                    </svg>
                `;
                
                title.textContent = 'Oops!';
            } else {
                modalContainer.classList.remove('error');
                modalContainer.classList.add('success');
                
                // Create animated SVG checkmark with circle (like your image)
                iconDiv.innerHTML = `
                    <svg viewBox="0 0 52 52">
                        <circle class="checkmark-circle" cx="26" cy="26" r="25"/>
                        <path class="checkmark" d="M14 27l7 7 16-16"/>
                    </svg>
                `;
                
                title.textContent = 'Success!';
            }

            // Show modal
            modal.classList.add('show');

            // Auto close after 3 seconds for success
            if (!isError) {
                setTimeout(() => {
                    closeNotificationModal();
                }, 3000);
            }
        }

        function closeNotificationModal() {
            const modal = document.getElementById('notificationModal');
            modal.classList.remove('show');
        }

        // Close modal when clicking overlay
        document.getElementById('notificationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeNotificationModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeNotificationModal();
            }
        });

        // Phone number validation
        document.getElementById('custPhone').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Add enter key support for search
        document.getElementById('searchCustomer').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('searchForm').submit();
            }
        });
    </script>
</body>
</html>