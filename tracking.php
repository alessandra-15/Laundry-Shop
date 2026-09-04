<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit();
}

$customerId = (int) $_SESSION['customer_id'];
$firstName = $_SESSION['first_name'] ?? '';
$lastName = $_SESSION['last_name'] ?? '';

$rows = [];
if ($stmt = $conn->prepare("SELECT Tracking_ID, Customer_ID, Schedule_ID, laundry_status, tracking_time, tracking_date FROM tracking WHERE Customer_ID = ? ORDER BY Schedule_ID DESC, tracking_date DESC, tracking_time DESC")) {
    $stmt->bind_param('i', $customerId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    $stmt->close();
}

$bySchedule = [];
$latestBySchedule = [];
foreach ($rows as $r) {
    $sid = $r['Schedule_ID'] ?? 'N/A';
    if (!isset($bySchedule[$sid])) { $bySchedule[$sid] = []; }
    $bySchedule[$sid][] = $r;
    if (!isset($latestBySchedule[$sid])) { $latestBySchedule[$sid] = $r; }
    
    if (strtolower(trim($r['laundry_status'])) === 'completed') {
        $update = $conn->prepare("UPDATE booking_online SET status = 'Inactive', completed_at = NOW() WHERE schedule_id = ?");
        $update->bind_param("i", $sid);
        $update->execute();
        $update->close();
    } else if (!empty($r['laundry_status'])) {
        $update = $conn->prepare("UPDATE booking_online SET status = 'Active' WHERE schedule_id = ?");
        $update->bind_param("i", $sid);
        $update->execute();
        $update->close();
    }
}

function h($v) { return htmlspecialchars((string)$v ?? '', ENT_QUOTES, 'UTF-8'); }
function statusBadgeClass($s) {
    $s = strtolower(trim((string)$s));
    if ($s === 'completed') return 'success';
    if ($s === 'ready') return 'info';
    if ($s === 'processing' || $s === 'in progress') return 'warning';
    if ($s === 'pending') return 'secondary';
    if ($s === 'cancelled') return 'danger';
    return 'primary';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Laundry - Mang TV Laundry Shop</title>
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
        
        /* Sidebar */
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
            transition: all 0.3s;
            margin: 0.25rem 0.75rem;
            border-radius: 10px;
            text-decoration: none;
        }
        
        .sidebar .nav-link i { font-size: 1.2rem; width: 24px; text-align: center; flex-shrink: 0; }
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
            transition: all 0.3s;
        }
        
        .toggle-btn:hover { background: var(--yellow); transform: scale(1.05); }

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
            box-shadow: 0 4px 12px rgba(0,83,122,0.2);
            font-size: 0.9rem;
        }

        /* Cards */
        .card-custom {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid rgba(168,232,249,0.2);
            margin-bottom: 2rem;
        }

        .card-header-custom {
            background: linear-gradient(135deg, var(--light-blue) 0%, rgba(168,232,249,0.3) 100%);
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(168,232,249,0.3);
            border-radius: 16px 16px 0 0;
        }

        .card-header-custom h6 {
            margin: 0;
            font-weight: 700;
            color: var(--dark-blue);
            font-size: 1.1rem;
        }

        .card-body-custom { padding: 1.5rem; }

        /* Timeline */
        .timeline { position: relative; padding-left: 30px; }
        .timeline::before { 
            content: ''; 
            position: absolute; 
            left: 12px; 
            top: 0; 
            bottom: 0; 
            width: 2px; 
            background: var(--light-blue); 
        }
        
        .tl-item { position: relative; padding: 14px 0 14px 10px; }
        .tl-item::before { 
            content: ''; 
            position: absolute; 
            left: 6px; 
            top: 22px; 
            width: 14px; 
            height: 14px; 
            background: #fff; 
            border: 3px solid var(--dark-blue); 
            border-radius: 50%; 
        }

        /* Table */
        .table-custom thead th { 
            background: var(--yellow);
            color: var(--dark-blue);
            font-weight: 600;
            padding: 1rem;
            border: none;
        }
        
        .table-custom tbody tr:hover { background: rgba(168,232,249,0.1); }
        .table-custom tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid rgba(168,232,249,0.2);
        }

        .badge-custom {
            padding: 0.4rem 0.75rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
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
                <p>Welcome, <?php echo h($firstName); ?>!</p>
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
                    <a href="tracking.php" class="nav-link active">
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
        <div class="topbar">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <button class="toggle-btn" id="toggleSidebar">
                        <i class="fa fa-bars"></i>
                    </button>
                    <div class="topbar-title">
                        <h5>Track Laundry</h5>
                        <small>Monitor your laundry status in real-time</small>
                    </div>
                </div>
                <div class="user-profile"><?php echo strtoupper(substr($firstName,0,1) . substr($lastName,0,1)); ?></div>
            </div>
        </div>

        <div class="container-fluid py-4 px-4">
            <!-- Recent Tracking -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <h6><i class="fas fa-list-ul me-2"></i>Recent Tracking</h6>
                </div>
                <div class="card-body-custom">
                    <?php if (empty($latestBySchedule)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3" style="opacity: 0.3;"></i>
                            <p class="text-muted">No tracking records yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Schedule #</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Tracking ID</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($latestBySchedule as $sid => $ev): ?>
                                    <tr>
                                        <td><strong><?php echo h($sid); ?></strong></td>
                                        <td><span class="badge bg-<?php echo statusBadgeClass($ev['laundry_status']); ?> badge-custom"><?php echo h($ev['laundry_status']); ?></span></td>
                                        <td><?php echo h($ev['tracking_date']); ?></td>
                                        <td><?php echo h($ev['tracking_time']); ?></td>
                                        <td><?php echo h($ev['Tracking_ID']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Detailed Timelines -->
            <?php if (!empty($bySchedule)): ?>
                <?php foreach ($bySchedule as $scheduleId => $events): ?>
                    <?php $latest = $events[0]; ?>
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Schedule #<?php echo h($scheduleId); ?></strong>
                                    <span class="badge bg-<?php echo statusBadgeClass($latest['laundry_status'] ?? ''); ?> badge-custom ms-2">
                                        <?php echo h($latest['laundry_status'] ?? ''); ?>
                                    </span>
                                </div>
                                <small class="text-muted">
                                    Last update: <?php echo h(($latest['tracking_date'] ?? '').' '.($latest['tracking_time'] ?? '')); ?>
                                </small>
                            </div>
                        </div>
                        <div class="card-body-custom">
                            <div class="timeline">
                                <?php foreach ($events as $ev): ?>
                                    <div class="tl-item">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <strong><?php echo h($ev['laundry_status'] ?? ''); ?></strong>
                                                <div style="color: #6c757d; font-size: 0.9rem;">Tracking ID: <?php echo h($ev['Tracking_ID'] ?? ''); ?></div>
                                            </div>
                                            <div class="text-end" style="color: #6c757d; font-size: 0.9rem;">
                                                <div><?php echo h($ev['tracking_date'] ?? ''); ?></div>
                                                <div><?php echo h($ev['tracking_time'] ?? ''); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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
    </script>
</body>
</html>