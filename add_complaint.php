<?php
// add_complaint.php — Add Complaint (MangTV palette)
include 'db_connect.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $customer_id = $conn->real_escape_string($_POST['customer_id']);
  $issue_description = $conn->real_escape_string($_POST['issue_description']);
  $status = $conn->real_escape_string($_POST['status']);
  $handled_by = $conn->real_escape_string($_POST['handled_by']);
  $date_reported = date('Y-m-d H:i:s');

  $sql = "INSERT INTO complaints (customer_id, issue_description, status, handled_by, date_reported) 
          VALUES ('$customer_id', '$issue_description', '$status', '$handled_by', '$date_reported')";

  if ($conn->query($sql)) {
    $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                  <i class="fas fa-check-circle me-2"></i><strong>Success!</strong> Complaint added successfully!
                  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
  } else {
    $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                  <i class="fas fa-exclamation-triangle me-2"></i><strong>Error!</strong> ' . htmlspecialchars($conn->error) . '
                  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
  }
}

// Determine current page for active nav highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Add Complaint — MangTV Admin</title>
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
    }
    
    .topbar-icon:hover {
      background: var(--light-blue);
      transform: translateY(-2px);
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
    
    .card-header-custom h5 {
      margin: 0;
      font-weight: 700;
      color: var(--dark-blue);
      font-size: 1.3rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    /* Form Styles */
    .form-label {
      color: var(--dark-blue);
      font-weight: 600;
      margin-bottom: 0.5rem;
      font-size: 0.9rem;
    }
    
    .form-control, .form-select {
      border: 2px solid #e9ecef;
      border-radius: 12px;
      padding: 0.8rem 1rem;
      font-size: 0.95rem;
      transition: all 0.3s;
    }
    
    .form-control:focus, .form-select:focus {
      border-color: var(--light-blue);
      box-shadow: 0 0 0 0.2rem rgba(168, 232, 249, 0.25);
    }
    
    textarea.form-control {
      resize: vertical;
      min-height: 120px;
    }

    /* Buttons */
    .btn-save {
      background: linear-gradient(135deg, var(--yellow) 0%, #ffe082 100%);
      color: var(--dark-blue);
      border: none;
      padding: 0.8rem 2rem;
      border-radius: 12px;
      font-weight: 700;
      font-size: 1rem;
      transition: all 0.3s;
      box-shadow: 0 4px 12px rgba(255,213,91,0.3);
    }
    
    .btn-save:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 20px rgba(255,213,91,0.4);
      color: var(--dark-blue);
    }
    
    .btn-back {
      background: var(--bg-light);
      color: var(--dark-blue);
      border: 2px solid rgba(168,232,249,0.6);
      padding: 0.8rem 2rem;
      border-radius: 12px;
      font-weight: 600;
      transition: all 0.3s;
    }
    
    .btn-back:hover {
      background: var(--light-blue);
      border-color: var(--light-blue);
      transform: translateY(-2px);
    }

    /* Alert */
    .alert {
      border-radius: 12px;
      border: none;
      padding: 1rem 1.25rem;
      margin-bottom: 1.5rem;
      animation: slideDown 0.4s ease-out;
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
          <a href="dashboard.php" class="nav-link <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
            <i class="fa fa-chart-line"></i>
            <span class="nav-text">Dashboard</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="customer_database.php" class="nav-link <?= $currentPage == 'customer_database.php' ? 'active' : '' ?>">
            <i class="fa fa-users"></i>
            <span class="nav-text">Customers</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="digital_record.php" class="nav-link <?= $currentPage == 'digital_record.php' ? 'active' : '' ?>">
            <i class="fa fa-database"></i>
            <span class="nav-text">Digital Record</span>
          </a>
        </li>
      </ul>
      
      <div class="nav-section-title">Operations</div>
      <ul class="nav flex-column">
        <li class="nav-item">
          <a href="order_scheduling.php" class="nav-link <?= $currentPage == 'order_scheduling.php' ? 'active' : '' ?>">
            <i class="fa fa-calendar-check"></i>
            <span class="nav-text">Schedules</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="walkin.php" class="nav-link <?= $currentPage == 'walkin.php' ? 'active' : '' ?>">
            <i class="fa fa-person-walking"></i>
            <span class="nav-text">Walk-in</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="payments.php" class="nav-link <?= $currentPage == 'payments.php' ? 'active' : '' ?>">
            <i class="fa fa-credit-card"></i>
            <span class="nav-text">Payments</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="financials.php" class="nav-link <?= $currentPage == 'financials.php' ? 'active' : '' ?>">
            <i class="fa fa-chart-pie"></i>
            <span class="nav-text">Financials</span>
          </a>
        </li>
      </ul>
      
      <div class="nav-section-title">Support</div>
      <ul class="nav flex-column">
        <li class="nav-item">
          <a href="add_complaint.php" class="nav-link <?= $currentPage == 'add_complaint.php' ? 'active' : '' ?>">
            <i class="fa fa-exclamation-circle"></i>
            <span class="nav-text">Complaints</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="employees.php" class="nav-link <?= $currentPage == 'employees.php' ? 'active' : '' ?>">
            <i class="fa fa-user-tie"></i>
            <span class="nav-text">Employees</span>
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
            <h5>Add New Complaint</h5>
            <small>Register a new complaint or issue reported by a customer</small>
          </div>
        </div>
        <div class="topbar-actions">
          <a href="#" class="topbar-icon">
            <i class="fa fa-bell"></i>
          </a>
          <a href="#" class="topbar-icon">
            <i class="fa fa-envelope"></i>
          </a>
          <a href="logout.php" class="btn btn-logout">
            <i class="fa fa-right-from-bracket me-2"></i>Logout
          </a>
        </div>
      </div>
    </div>

    <div class="container-fluid py-4 px-4">
      <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
          <div class="card-custom">
            <div class="card-header-custom">
              <h5><i class="fa fa-plus-circle me-2"></i>Complaint Details</h5>
            </div>
            <div class="card-body p-4">
              <?= $message ?>
              
              <form method="POST">
                <div class="mb-4">
                  <label class="form-label">Customer ID</label>
                  <input type="text" name="customer_id" class="form-control" required placeholder="Enter customer ID">
                  <small class="text-muted">Enter the unique customer identifier</small>
                </div>
                
                <div class="mb-4">
                  <label class="form-label">Issue Description</label>
                  <textarea name="issue_description" class="form-control" rows="5" required placeholder="Describe the issue in detail..."></textarea>
                  <small class="text-muted">Provide a detailed description of the customer's complaint</small>
                </div>
                
                <div class="row">
                  <div class="col-md-6 mb-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                      <option value="Pending">Pending</option>
                      <option value="In Progress">In Progress</option>
                      <option value="Resolved">Resolved</option>
                    </select>
                  </div>
                  <div class="col-md-6 mb-4">
                    <label class="form-label">Handled By</label>
                    <input type="text" name="handled_by" class="form-control" placeholder="Staff name (optional)">
                  </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mt-4">
                  <a href="dashboard.php" class="btn btn-back">
                    <i class="fa fa-arrow-left me-2"></i>Back to Dashboard
                  </a>
                  <button type="submit" class="btn btn-save">
                    <i class="fa fa-save me-2"></i>Save Complaint
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>

      <footer>
        <p class="mb-0">© <?= date('Y') ?> <strong>MangTV Laundry Shop</strong> - All Rights Reserved</p>
      </footer>
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
  </script>
</body>
</html>
<?php $conn->close(); ?>