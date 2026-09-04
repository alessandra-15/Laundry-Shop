<?php
session_start();
include 'db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : null;
    $user_id    = (int)$_POST['user_id'];
    $message    = trim($_POST['message']);
    $status     = $_POST['status'];

    if (empty($message) || $user_id <= 0) {
        $error = "Please fill in all required fields.";
    } else {
        include 'notification_helpers.php';

        $notif_id = createNotification($conn, $booking_id, $user_id, $message, $status);

        if ($notif_id) {
            $success = "Notification sent successfully!";
        } else {
            $error = "Failed to send notification.";
        }
    }
}

// Get all users for dropdown
$users_query  = "SELECT customer_id, first_name, last_name, email FROM customer ORDER BY first_name, last_name";
$users_result = $conn->query($users_query);

// Get recent notifications (per user, with created_at)
$notifications_query = "
    SELECT n.*, 
           c.first_name, 
           c.last_name, 
           c.email,
           b.service, 
           b.status AS booking_status
    FROM notifications_user n
    LEFT JOIN customer c ON n.user_id = c.customer_id
    LEFT JOIN booking_online b ON n.booking_id = b.id
    ORDER BY n.created_at DESC
    LIMIT 20
";
$notifications_result = $conn->query($notifications_query);

function formatNotifDate($datetime) {
    return date('M d, Y h:i A', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Notification - MangTV Laundry Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Add your custom styles here (same as in your original code) */
        /* Your custom CSS code will be the same */
    </style>
</head>
<body>
    <!-- Navbar and Content -->

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-bell me-2"></i>Send Notification to User</h5>
                    </div>
                    <div class="card-body-custom">
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Notification Form -->
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="user_id" class="form-label">
                                            <i class="fas fa-user me-1"></i>Select User <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="user_id" name="user_id" required>
                                            <option value="">Choose a user...</option>
                                            <?php while ($user = $users_result->fetch_assoc()): ?>
                                                <option value="<?php echo $user['customer_id']; ?>">
                                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')'); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="booking_id" class="form-label">
                                            <i class="fas fa-clipboard-list me-1"></i>Booking ID (Optional)
                                        </label>
                                        <input type="number" class="form-control" id="booking_id" name="booking_id"
                                               placeholder="Leave empty for general notification">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="status" class="form-label">
                                    <i class="fas fa-info-circle me-1"></i>Status
                                </label>
                                <select class="form-select" id="status" name="status">
                                    <option value="sent">Sent (Unread)</option>
                                    <option value="read">Read</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="message" class="form-label">
                                    <i class="fas fa-comment me-1"></i>Message <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control" id="message" name="message" rows="4"
                                          placeholder="Enter your notification message..." required></textarea>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-custom">
                                    <i class="fas fa-paper-plane me-2"></i>Send Notification
                                </button>
                                <button type="reset" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo me-2"></i>Reset Form
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Notifications -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-history me-2"></i>Recent Notifications</h5>
                    </div>
                    <div class="card-body-custom">
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Booking</th>
                                        <th>Message</th>
                                        <th>Status</th>
                                        <th>Sent At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($notifications_result && $notifications_result->num_rows > 0): ?>
                                        <?php while ($notif = $notifications_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong>#<?php echo $notif['notif_id']; ?></strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($notif['first_name'] . ' ' . $notif['last_name']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($notif['email']); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($notif['booking_id']): ?>
                                                    <strong>#<?php echo $notif['booking_id']; ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($notif['service'] ?: 'N/A'); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">General</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($notif['message']); ?></td>
                                            <td>
                                                <span class="badge badge-custom status-<?php echo $notif['status']; ?>">
                                                    <?php echo ucfirst($notif['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatNotifDate($notif['created_at']); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                                <p class="text-muted mb-0">No notifications sent yet</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>
