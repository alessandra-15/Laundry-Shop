<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

$customerId   = $_SESSION['customer_id'];
$firstName    = $_SESSION['first_name'] ?? '';
$lastName     = $_SESSION['last_name'] ?? '';
$fullName     = trim($firstName . ' ' . $lastName);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['rating'], $_POST['comment'])) {
    $booking_id = (int)$_POST['booking_id'];
    $rating     = (int)$_POST['rating'];
    $comment    = trim($_POST['comment']);

    $stmt = $conn->prepare("SELECT id FROM feedback WHERE user_id = ? AND booking_id = ?");
    $stmt->bind_param("ii", $customerId, $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE feedback SET rating = ?, comment = ?, created_at = NOW() WHERE user_id = ? AND booking_id = ?");
        $stmt->bind_param("isii", $rating, $comment, $customerId, $booking_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO feedback (user_id, booking_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiis", $customerId, $booking_id, $rating, $comment);
    }

    if ($stmt->execute()) {
        $_SESSION['feedback_success'] = "Your feedback has been submitted successfully!";
    } else {
        $_SESSION['feedback_error'] = "There was an error submitting your feedback. Please try again.";
    }
    $stmt->close();

    header("Location: feedback.php");
    exit();
}

$feedback_message = '';
if (isset($_SESSION['feedback_success'])) {
    $feedback_message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>' . htmlspecialchars($_SESSION['feedback_success']) . '
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>';
    unset($_SESSION['feedback_success']);
} elseif (isset($_SESSION['feedback_error'])) {
    $feedback_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>' . htmlspecialchars($_SESSION['feedback_error']) . '
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>';
    unset($_SESSION['feedback_error']);
}

$userBookings = [];
$stmt = $conn->prepare("
    SELECT 
        b.id AS booking_id, 
        b.delivery_option, 
        b.dropoff_date,
        f.rating AS existing_rating,
        f.comment AS existing_comment
    FROM booking_online b
    LEFT JOIN feedback f ON b.id = f.booking_id AND f.user_id = ?
    WHERE b.customer_name = ? AND b.status = 'Completed'
    ORDER BY b.dropoff_date DESC
");
$stmt->bind_param("is", $customerId, $fullName);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $userBookings[] = $row;
$stmt->close();

$myFeedbacks = [];
$stmt = $conn->prepare("
    SELECT f.*, b.delivery_option, b.dropoff_date 
    FROM feedback f
    LEFT JOIN booking_online b ON f.booking_id = b.id
    WHERE f.user_id = ? 
    ORDER BY f.created_at DESC
");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $myFeedbacks[] = $row;
$stmt->close();

$otherFeedbacks = [];
$stmt = $conn->prepare("
    SELECT f.*, u.first_name, u.last_name 
    FROM feedback f
    JOIN customer_info u ON f.user_id = u.Customer_ID
    WHERE f.user_id != ?
    ORDER BY f.created_at DESC
");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $otherFeedbacks[] = $row;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Feedback - Mang TV Laundry</title>
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

/* Form */
.form-label {
    color: var(--dark-blue);
    font-weight: 600;
    margin-bottom: 8px;
}

.form-control, .form-select {
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    padding: 12px 15px;
    transition: all 0.3s;
}

.form-control:focus, .form-select:focus {
    border-color: var(--yellow);
    box-shadow: 0 0 0 3px rgba(255, 211, 91, 0.2);
}

/* Star Rating */
.star-rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    gap: 5px;
}
.star-rating input { display: none; }
.star-rating label {
    font-size: 25px;
    color: #ddd;
    cursor: pointer;
    transition: color 0.2s ease;
}
.star-rating input:checked ~ label { color: #ffd700; }
.star-rating label:hover,
.star-rating label:hover ~ label { color: #ffd700; }

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

.btn-submit {
    background: linear-gradient(135deg, var(--dark-blue) 0%, #003d5c 100%);
    color: white;
    border: none;
    padding: 12px 28px;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s;
    width: 100%;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,83,122,0.3);
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
                <a href="feedback.php" class="nav-link active">
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

<!-- Main content -->
<main id="mainContent">
    <div class="topbar">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="toggle-btn" id="toggleSidebar">
                    <i class="fa fa-bars"></i>
                </button>
                <div class="topbar-title">
                    <h5>Feedback</h5>
                    <small>Share your experience and view reviews</small>
                </div>
            </div>
            <div class="user-profile"><?php echo strtoupper(substr($firstName,0,1) . substr($lastName,0,1)); ?></div>
        </div>
    </div>

    <div class="container-fluid py-4 px-4">
        <?php echo $feedback_message; ?>

        <!-- Feedback Submission -->
        <div class="card-custom">
            <div class="card-header-custom">
                <h6><i class="fas fa-pen me-2"></i>Submit Feedback</h6>
            </div>
            <div class="card-body-custom">
                <?php if(empty($userBookings)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-box-open fa-3x text-muted mb-3" style="opacity: 0.3;"></i>
                        <p class="text-muted">No completed bookings yet to review.</p>
                    </div>
                <?php else: ?>
                    <form method="POST" id="feedbackForm">
                        <div class="mb-3">
                            <label class="form-label">Select Booking</label>
                            <select name="booking_id" class="form-select" required onchange="loadExistingFeedback(this)">
                                <option value="">Choose a completed booking...</option>
                                <?php foreach($userBookings as $b): ?>
                                    <option value="<?php echo $b['booking_id']; ?>"
                                        data-rating="<?php echo htmlspecialchars($b['existing_rating'] ?? ''); ?>"
                                        data-comment="<?php echo htmlspecialchars($b['existing_comment'] ?? ''); ?>">
                                        Booking #<?php echo $b['booking_id']; ?> |
                                        <?php echo htmlspecialchars($b['delivery_option']); ?> |
                                        <?php echo date('M d, Y', strtotime($b['dropoff_date'])); ?>
                                        <?php if(!empty($b['existing_rating'])): ?>
                                            | <?php echo str_repeat('⭐', (int)$b['existing_rating']); ?> (Reviewed)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Your Rating</label>
                            <div class="star-rating mb-2">
                                <?php for($i=5; $i>=1; $i--): ?>
                                    <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" required>
                                    <label for="star<?php echo $i; ?>">⭐</label>
                                <?php endfor; ?>
                            </div>
                            <div id="ratingText" class="text-muted small"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Your Comments</label>
                            <textarea name="comment" class="form-control" rows="4" placeholder="Tell us about your experience..." required></textarea>
                        </div>
                        <button type="submit" class="btn-submit" id="submitBtn">
                            <i class="fas fa-paper-plane me-2"></i>Submit Feedback
                        </button>
                    </form>

                    <script>
                    function loadExistingFeedback(select) {
                        const option = select.options[select.selectedIndex];
                        const rating = option.dataset.rating;
                        const comment = option.dataset.comment;

                        document.querySelectorAll('input[name="rating"]').forEach(input => input.checked = false);
                        document.querySelector('textarea[name="comment"]').value = '';

                        if (rating) {
                            document.querySelector(`#star${rating}`).checked = true;
                            document.querySelector('textarea[name="comment"]').value = comment;
                            document.querySelector('#submitBtn').innerHTML = '<i class="fas fa-edit me-2"></i>Update Feedback';
                        } else {
                            document.querySelector('#submitBtn').innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Feedback';
                        }
                        updateRatingText();
                    }

                    document.querySelectorAll('input[name="rating"]').forEach(input => {
                        input.addEventListener('change', updateRatingText);
                    });

                    function updateRatingText() {
                        const ratingTexts = { 5: "Excellent", 4: "Very Good", 3: "Good", 2: "Fair", 1: "Poor" };
                        const checkedRating = document.querySelector('input[name="rating"]:checked');
                        const ratingText = document.getElementById('ratingText');

                        if (checkedRating) {
                            ratingText.textContent = ratingTexts[parseInt(checkedRating.value)] || '';
                        } else {
                            ratingText.textContent = '';
                        }
                    }
                    </script>
                <?php endif; ?>
            </div>
        </div>

        <!-- My Feedbacks -->
        <div class="card-custom">
            <div class="card-header-custom">
                <h6><i class="fas fa-history me-2"></i>My Feedback History</h6>
            </div>
            <div class="card-body-custom">
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Rating</th>
                                <th>Comment</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($myFeedbacks)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4">
                                        <i class="fas fa-comments fa-2x text-muted mb-2" style="opacity: 0.3;"></i>
                                        <p class="text-muted mb-0">No feedback submitted yet.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($myFeedbacks as $f): ?>
                                    <tr>
                                        <td><strong>#<?php echo htmlspecialchars($f['booking_id']); ?></strong></td>
                                        <td style="color: #ffc107;"><?php echo str_repeat('⭐', (int)$f['rating']); ?></td>
                                        <td><?php echo htmlspecialchars($f['comment']); ?></td>
                                        <td><?php echo date("M d, Y h:i A", strtotime($f['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Community Feedbacks -->
        <div class="card-custom">
            <div class="card-header-custom">
                <h6><i class="fas fa-users me-2"></i>Community Reviews</h6>
            </div>
            <div class="card-body-custom">
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Booking ID</th>
                                <th>Rating</th>
                                <th>Comment</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($otherFeedbacks)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        <i class="fas fa-users fa-2x text-muted mb-2" style="opacity: 0.3;"></i>
                                        <p class="text-muted mb-0">No community reviews available yet.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($otherFeedbacks as $f): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($f['first_name'].' '.$f['last_name']); ?></td>
                                        <td><strong>#<?php echo htmlspecialchars($f['booking_id']); ?></strong></td>
                                        <td style="color: #ffc107;"><?php echo str_repeat('⭐', (int)$f['rating']); ?></td>
                                        <td><?php echo htmlspecialchars($f['comment']); ?></td>
                                        <td><?php echo date("M d, Y h:i A", strtotime($f['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
</script>
</body>
</html>