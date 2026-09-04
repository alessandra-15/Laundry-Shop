<?php
$page_title = "Book Service - Mang TV Laundry Shop";
$page_header = "Book Your Laundry Service";
$page_description = "Select your services and schedule your laundry pickup";

include 'db_connect.php';
session_start();

$booking_error = '';
$account_type = '';
$student_discount = false;

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];
$firstName = $_SESSION['first_name'] ?? '';
$lastName = $_SESSION['last_name'] ?? '';

$stmt = $conn->prepare("SELECT account_type FROM customer_info WHERE Customer_ID = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();
$customer_data = $result->fetch_assoc();
$stmt->close();

$account_type = $customer_data['account_type'];
$student_discount = ($account_type === 'Student');
$_SESSION['account_type'] = $account_type;

$discount_notice = '';
if ($student_discount) {
    $discount_notice = '<div class="alert alert-info mt-3">
        <i class="fas fa-graduation-cap"></i> Student discount (10%) will be applied to your order.
    </div>';
}

$admin_id = 1;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $customerName = trim($_POST['customerName']);
    $contactNumber = '+63' . trim($_POST['contactNumber']);
    $address = trim($_POST['address']);
    $deliveryOption = trim($_POST['deliveryOption']);
    $dropoffDate = trim($_POST['dropoffDate']);
    $dropoffTime = trim($_POST['dropoffTime']);
    $specialInstructions = trim($_POST['specialInstructions']);
    $addons = [];

    if (isset($_POST['extraDry'])) $addons[] = 'Extra Dry';
    if (isset($_POST['detergent'])) $addons[] = 'Liquid Detergent';
    if (isset($_POST['fabcon'])) $addons[] = 'Fabric Conditioner';

    $addonsText = implode(', ', $addons);
    $service = trim(strip_tags($_POST['service'] ?? ''));
    
    if (strlen($service) > 255) {
        $service = substr($service, 0, 255);
    }

    if (empty($service)) {
        $booking_error = 'Please select at least one service before booking.';
    }

    $totalAmount = isset($_POST['totalAmount']) ? floatval($_POST['totalAmount']) : 0;
    $discount = $student_discount ? ($totalAmount * 0.10) : 0;
    
    $status = 'Pending';
    $timestamp = date('Y-m-d H:i:s');

    // First insert into booking_online WITHOUT schedule_id (will be updated later)
    $stmt = $conn->prepare("INSERT INTO booking_online 
        (customer_id, admin_id, customer_name, contact_number, address, delivery_option, dropoff_date, dropoff_time, special_instructions, service, addons, `timestamp`, status, total_amount, discount)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if ($stmt === false) {
        $booking_error = 'Database prepare error: ' . $conn->error;
    }

    if (empty($booking_error)) {
        $stmt->bind_param(
            "iisssssssssssdd",
            $customer_id, $admin_id, $customerName, $contactNumber,
            $address, $deliveryOption, $dropoffDate, $dropoffTime,
            $specialInstructions, $service, $addonsText, $timestamp,
            $status, $totalAmount, $discount
        );

        if ($stmt->execute()) {
            $booking_id = $conn->insert_id;
            
            // The trigger already created a schedule entry, now get its ID
            $schedule_query = $conn->prepare("SELECT Schedule_ID FROM schedule WHERE Customer_ID = ? ORDER BY Schedule_ID DESC LIMIT 1");
            $schedule_query->bind_param("i", $customer_id);
            $schedule_query->execute();
            $schedule_result = $schedule_query->get_result();
            
            if ($schedule_row = $schedule_result->fetch_assoc()) {
                $schedule_id = $schedule_row['Schedule_ID'];
                
                // Update the booking with the schedule_id
                $update_stmt = $conn->prepare("UPDATE booking_online SET schedule_id = ? WHERE id = ?");
                $update_stmt->bind_param("ii", $schedule_id, $booking_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
            $schedule_query->close();
            
            // Update booking status to Processing
            $update_status = $conn->prepare("UPDATE booking_online SET status = 'Processing' WHERE id = ?");
            if ($update_status) {
                $update_status->bind_param("i", $booking_id);
                $update_status->execute();
                $update_status->close();
            }
            
            header("Location: payment.php?booking_id=" . $booking_id);
            exit;
        } else {
            $booking_error = 'Error saving booking. Please try again: ' . $stmt->error;
        }
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
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
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
        }
        
        .sidebar::-webkit-scrollbar { width: 6px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.1); }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 3px; }
        
        .sidebar.collapsed { width: var(--sidebar-collapsed); }
        .sidebar.collapsed .brand-text,
        .sidebar.collapsed .nav-text { opacity: 0; visibility: hidden; }
        
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
            transition: all 0.3s;
            margin: 0.25rem 0.75rem;
            border-radius: 10px;
            text-decoration: none;
        }
        
        .sidebar .nav-link i { font-size: 1.2rem; width: 24px; text-align: center; flex-shrink: 0; }
        .sidebar .nav-link .nav-text { transition: all 0.3s; white-space: nowrap; }
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

        /* Main Content */
        main { 
            margin-left: var(--sidebar-width); 
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,83,122,0.2);
            transition: all 0.3s;
            font-size: 0.9rem;
        }

        /* Service Cards */
        .service-section { margin-bottom: 30px; }
        
        .section-title {
            color: var(--dark-blue);
            font-weight: bold;
            font-size: 1.3rem;
            margin-bottom: 15px;
            padding: 12px 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .service-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .service-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border: 2px solid transparent;
            cursor: pointer;
            text-align: center;
        }

        .service-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
            transform: translateY(-3px);
        }

        .service-card.selected {
            border-color: var(--yellow);
            background-color: #fffef5;
        }

        .service-card i {
            font-size: 32px;
            color: var(--dark-blue);
            margin-bottom: 10px;
        }

        .service-card h4 {
            color: var(--dark-blue);
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 1.1rem;
        }

        .service-card .price {
            color: white;
            font-weight: bold;
            font-size: 1rem;
            background: var(--dark-blue);
            padding: 6px 12px;
            border-radius: 6px;
            display: inline-block;
        }

        .booking-form {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .form-label {
            color: var(--dark-blue);
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 15px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--yellow);
            box-shadow: 0 0 0 3px rgba(255, 211, 91, 0.2);
        }

        .addon-checkbox {
            background: var(--light-blue);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .addon-checkbox:hover { background: #8dd9f0; }

        .service-summary {
            background: var(--light-blue);
            border-radius: 15px;
            padding: 25px;
            margin-top: 30px;
        }

        .service-summary h4 {
            color: var(--dark-blue);
            font-weight: bold;
            margin-bottom: 20px;
        }

        .selected-service {
            background: white;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-book {
            background: linear-gradient(135deg, var(--dark-blue) 0%, #003d5c 100%);
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 10px;
            font-weight: bold;
            font-size: 1.2rem;
            width: 100%;
            transition: all 0.3s ease;
        }

        .btn-book:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,83,122,0.3);
        }

        .input-group {
            display: flex;
            align-items: stretch;
        }
        
        .input-group-text {
            background: var(--light-blue);
            border: 2px solid #e0e0e0;
            border-right: 2px solid #e0e0e0;
            border-radius: 10px 0 0 10px;
            color: var(--dark-blue);
            font-weight: 600;
            padding: 12px 15px;
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .input-group .form-control {
            border-left: 2px solid #e0e0e0;
            border-radius: 0 10px 10px 0;
            margin-bottom: 20px;
        }

        @media (max-width:992px) {
            .sidebar { width: var(--sidebar-collapsed); }
            .sidebar .brand-text, .sidebar .nav-text { opacity: 0; visibility: hidden; }
            main { margin-left: var(--sidebar-collapsed); }
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
                    <a href="userdashboard.php" class="nav-link">
                        <i class="fas fa-home"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="booking.php" class="nav-link active">
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
                        <h5>New Booking</h5>
                        <small>Select your services and schedule pickup</small>
                    </div>
                </div>
                <div class="user-profile"><?php echo strtoupper(substr($firstName,0,1) . substr($lastName,0,1)); ?></div>
            </div>
        </div>

        <div class="container-fluid py-4 px-4">
            <?php echo $discount_notice; ?>
            
            <!-- Full Service -->
            <div class="service-section">
                <div class="section-title">Full Service</div>
                <div class="service-cards">
                    <div class="service-card" onclick="toggleService('full-service', 'Full Service')" id="card-full-service">
                        <i class="fas fa-check-circle"></i>
                        <h4>Full Service</h4>
                        <p>Wash, dry, fold with detergent & fabric conditioner</p>
                        <span class="price">₱200/load</span>
                        <div style="margin-top: 10px; color: #666; font-size: 0.85rem;">7kg per load</div>
                    </div>
                </div>
            </div>

            <!-- Self Service -->
            <div class="service-section">
                <div class="section-title">Self Service</div>
                <div class="service-cards">
                    <div class="service-card" onclick="toggleService('wash-only', 'Wash Only')" id="card-wash-only">
                        <i class="fas fa-water"></i>
                        <h4>Wash Only</h4>
                        <p>Washing service only</p>
                        <span class="price">₱80/load</span>
                    </div>
                    <div class="service-card" onclick="toggleService('dry-only', 'Dry Only')" id="card-dry-only">
                        <i class="fas fa-wind"></i>
                        <h4>Dry Only</h4>
                        <p>Drying service only</p>
                        <span class="price">₱70/load</span>
                    </div>
                </div>
            </div>

            <!-- Special Items -->
            <div class="service-section">
                <div class="section-title">Special Items</div>
                <div class="service-cards">
                    <div class="service-card" onclick="toggleService('blanket', 'Blanket/Bedsheet')" id="card-blanket">
                        <i class="fas fa-bed"></i>
                        <h4>Blanket/Bedsheet</h4>
                        <p>Thick, up to 3kg</p>
                        <span class="price">₱200/load</span>
                    </div>
                    <div class="service-card" onclick="toggleService('comforter', 'Comforter')" id="card-comforter">
                        <i class="fas fa-couch"></i>
                        <h4>Comforter</h4>
                        <p>1 piece per load</p>
                        <span class="price">₱200/piece</span>
                    </div>
                </div>
            </div>

            <!-- Booking Form -->
            <div class="booking-form">
                <h3>Booking Details</h3>
                <form id="bookingForm" method="POST" action="">
                    <?php if (!empty($booking_error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($booking_error); ?></div>
                    <?php endif; ?>
                    <input type="hidden" name="service" id="serviceInput" value="">
                    <input type="hidden" name="totalAmount" id="totalAmountInput" value="">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Customer Name *</label>
                            <input type="text" class="form-control" name="customerName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Number *</label>
                            <div class="input-group">
                                <span class="input-group-text">+63</span>
                                <input type="tel" class="form-control" name="contactNumber" placeholder="9xxxxxxxxx" required pattern="[9][0-9]{9}">
                            </div>
                        </div>
                    </div>

                    <label class="form-label">Address *</label>
                    <textarea class="form-control" name="address" rows="2" required></textarea>

                    <label class="form-label">Delivery Option *</label>
                    <select class="form-select" name="deliveryOption" onchange="updateServiceSummary()" required>
                        <option value="">Select option</option>
                        <option value="pickup-only">Pickup Only (₱20)</option>
                        <option value="pickup-delivery">Pickup & Delivery (₱35)</option>
                    </select>

                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Drop-off Date *</label>
                            <input type="date" class="form-control" name="dropoffDate" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Drop-off Time *</label>
                            <select class="form-select" name="dropoffTime" required>
                                <option value="">Select time</option>
                                <option value="7-9">7:00 AM - 9:00 AM</option>
                                <option value="9-11">9:00 AM - 11:00 AM</option>
                                <option value="11-1">11:00 AM - 1:00 PM</option>
                                <option value="1-3">1:00 PM - 3:00 PM</option>
                                <option value="3-5">3:00 PM - 5:00 PM</option>
                                <option value="5-6">5:00 PM - 6:00 PM</option>
                            </select>
                        </div>
                    </div>

                    <label class="form-label">Special Instructions</label>
                    <textarea class="form-control" name="specialInstructions" rows="3"></textarea>

                    <label class="form-label">Add-ons</label>
                    <div class="addon-checkbox">
                        <input type="checkbox" name="extraDry" onchange="updateServiceSummary()">
                        <label>Extra Dry (+₱15)</label>
                    </div>
                    <div class="addon-checkbox">
                        <input type="checkbox" name="detergent" onchange="updateServiceSummary()">
                        <label>Liquid Detergent (+₱10)</label>
                    </div>
                    <div class="addon-checkbox">
                        <input type="checkbox" name="fabcon" onchange="updateServiceSummary()">
                        <label>Fabric Conditioner (+₱10)</label>
                    </div>

                    <div class="service-summary">
                        <h4>Selected Services</h4>
                        <div id="selectedServicesList">
                            <p style="color: #666;">No services selected yet.</p>
                        </div>
                    </div>

                    <button type="button" class="btn-book mt-3" onclick="showConfirmModal()">
                        <i class="fas fa-calendar-check"></i> Book Now
                    </button>
                </form>
            </div>
        </div>
    </main>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmBookingModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--dark-blue); color: white;">
                    <h5 class="modal-title">Confirm Your Booking</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="confirmationDetails"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Edit</button>
                    <button type="button" class="btn btn-primary" style="background: var(--dark-blue);" onclick="submitBooking()">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const toggleBtn = document.getElementById('toggleSidebar');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });

        let selectedServices = new Map();
        const isStudent = <?php echo $student_discount ? 'true' : 'false'; ?>;

        function toggleService(serviceId, serviceName) {
            const card = document.getElementById('card-' + serviceId);
            if (selectedServices.has(serviceId)) {
                selectedServices.delete(serviceId);
                card.classList.remove('selected');
            } else {
                selectedServices.set(serviceId, serviceName);
                card.classList.add('selected');
            }
            updateServiceSummary();
        }

        function calculateTotal() {
            let subtotal = 0;
            
            selectedServices.forEach((name, id) => {
                switch(id) {
                    case 'full-service': subtotal += 200; break;
                    case 'wash-only': subtotal += 80; break;
                    case 'dry-only': subtotal += 70; break;
                    case 'blanket':
                    case 'comforter': subtotal += 200; break;
                }
            });

            if (document.querySelector('[name="extraDry"]').checked) subtotal += 15;
            if (document.querySelector('[name="detergent"]').checked) subtotal += 10;
            if (document.querySelector('[name="fabcon"]').checked) subtotal += 10;

            let discount = isStudent ? Math.round((subtotal * 0.10) * 100) / 100 : 0;
            const deliveryOption = document.querySelector('[name="deliveryOption"]').value;
            const deliveryFee = deliveryOption === 'pickup-only' ? 20 : deliveryOption === 'pickup-delivery' ? 35 : 0;
            const total = subtotal - discount + deliveryFee;

            return { subtotal, discount, deliveryFee, total, isStudent };
        }

        function updateServiceSummary() {
            const listContainer = document.getElementById('selectedServicesList');
            const pricing = calculateTotal();
            let html = '';

            if (selectedServices.size > 0) {
                selectedServices.forEach((name, id) => {
                    let price = '';
                    switch(id) {
                        case 'full-service': price = '₱200/load'; break;
                        case 'wash-only': price = '₱80/load'; break;
                        case 'dry-only': price = '₱70/load'; break;
                        case 'blanket':
                        case 'comforter': price = '₱200/piece'; break;
                    }
                    html += `<div class="selected-service">
                        <i class="fas fa-check-circle"></i>
                        <span>${name}</span>
                        <span class="ms-auto">${price}</span>
                    </div>`;
                });
            }

            if (pricing.subtotal > 0) {
                html += `<div style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #ccc;">
                    <div class="selected-service">
                        <span>Subtotal</span>
                        <span class="ms-auto">₱${pricing.subtotal.toFixed(2)}</span>
                    </div>`;
                
                if (pricing.isStudent) {
                    html += `<div class="selected-service" style="background: #e8f7ff;">
                        <span><i class="fas fa-graduation-cap"></i> Student Discount (10%)</span>
                        <span class="ms-auto text-success">-₱${pricing.discount.toFixed(2)}</span>
                    </div>`;
                }

                if (pricing.deliveryFee > 0) {
                    html += `<div class="selected-service">
                        <span>Delivery Fee</span>
                        <span class="ms-auto">₱${pricing.deliveryFee.toFixed(2)}</span>
                    </div>`;
                }

                html += `<div class="selected-service" style="background: var(--dark-blue); color: white;">
                    <strong>Total Amount</strong>
                    <span class="ms-auto">₱${pricing.total.toFixed(2)}</span>
                </div></div>`;
            } else {
                html = '<p style="color: #666;">No services selected yet.</p>';
            }

            listContainer.innerHTML = html;
            document.getElementById('serviceInput').value = Array.from(selectedServices.values()).join(', ');
            document.getElementById('totalAmountInput').value = pricing.total.toFixed(2);
        }

        function showConfirmModal() {
            if (selectedServices.size === 0) {
                alert('Please select at least one service!');
                return;
            }
            
            const form = document.getElementById('bookingForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const modal = new bootstrap.Modal(document.getElementById('confirmBookingModal'));
            const detailsContainer = document.getElementById('confirmationDetails');
            const pricing = calculateTotal();
            
            let html = `<h6>Services Selected:</h6><ul>`;
            selectedServices.forEach((name) => {
                html += `<li>${name}</li>`;
            });
            html += `</ul><hr>`;
            html += `<p><strong>Name:</strong> ${document.querySelector('[name="customerName"]').value}</p>`;
            html += `<p><strong>Contact:</strong> +63${document.querySelector('[name="contactNumber"]').value}</p>`;
            html += `<p><strong>Total:</strong> ₱${pricing.total.toFixed(2)}</p>`;
            
            detailsContainer.innerHTML = html;
            modal.show();
        }

        function submitBooking() {
            document.getElementById('bookingForm').submit();
        }
    </script>
</body>
</html>