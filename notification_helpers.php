<?php
// notification_helpers.php - Helper functions for notifications_admin

/**
 * Create a new notification
 */
function createNotification($conn, $booking_id, $user_id, $message, $status = 'pending') {
    $stmt = $conn->prepare("
        INSERT INTO notifications_admin (booking_id, user_id, message, status, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    $stmt->bind_param("iiss", $booking_id, $user_id, $message, $status);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Create notification for new online booking
 * Call this from booking_online.php after successful booking
 */
function createBookingNotification($conn, $booking_id, $customer_name, $service, $dropoff_date) {
    // Format the message
    $message = "New online booking from {$customer_name} for {$service} scheduled on " . date('M j, Y', strtotime($dropoff_date));
    
    // User ID 0 means system notification (no specific user)
    return createNotification($conn, $booking_id, 0, $message, 'pending');
}

/**
 * Get full booking details and create notification (alternative method)
 * This fetches booking details automatically
 */
function createBookingNotificationById($conn, $booking_id) {
    // Get booking details
    $stmt = $conn->prepare("
        SELECT customer_name, service, dropoff_date 
        FROM booking_online 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $message = "New online booking from {$row['customer_name']} for {$row['service']} scheduled on " . date('M j, Y', strtotime($row['dropoff_date']));
        $stmt->close();
        return createNotification($conn, $booking_id, 0, $message, 'pending');
    }
    
    $stmt->close();
    return false;
}

/**
 * Create notification for booking status change
 */
function createBookingStatusNotification($conn, $booking_id, $customer_name, $old_status, $new_status) {
    $message = "Booking #{$booking_id} status changed from {$old_status} to {$new_status} for customer {$customer_name}";
    return createNotification($conn, $booking_id, 0, $message, 'pending');
}

/**
 * Create notification for payment received
 */
function createPaymentNotification($conn, $booking_id, $customer_name, $amount) {
    $message = "Payment of ₱" . number_format($amount, 2) . " received from {$customer_name} for booking #{$booking_id}";
    return createNotification($conn, $booking_id, 0, $message, 'pending');
}

/**
 * Create notification for payment status change
 */
function createPaymentStatusNotification($conn, $booking_id, $customer_name, $payment_status) {
    $message = "Payment status updated to '{$payment_status}' for booking #{$booking_id} - {$customer_name}";
    return createNotification($conn, $booking_id, 0, $message, 'pending');
}

/**
 * Get unread notification count
 */
function getUnreadCount($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM notifications_admin WHERE status = 'pending'");
    $row = $result->fetch_assoc();
    return (int)($row['count'] ?? 0);
}

/**
 * Get recent notifications
 */
function getRecentNotifications($conn, $limit = 10, $unread_only = false) {
    $where = $unread_only ? "WHERE status = 'pending'" : "";
    $query = "
        SELECT notif_id, booking_id, user_id, message, status, created_at
        FROM notifications_admin
        {$where}
        ORDER BY created_at DESC
        LIMIT {$limit}
    ";
    
    return $conn->query($query);
}

/**
 * Mark notification as read
 */
function markAsRead($conn, $notif_id) {
    $stmt = $conn->prepare("UPDATE notifications_admin SET status = 'read' WHERE notif_id = ?");
    $stmt->bind_param("i", $notif_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Mark all notifications as read
 */
function markAllAsRead($conn) {
    return $conn->query("UPDATE notifications_admin SET status = 'read' WHERE status = 'pending'");
}

/**
 * Delete old read notifications (cleanup - optional)
 */
function deleteOldNotifications($conn, $days = 30) {
    $stmt = $conn->prepare("
        DELETE FROM notifications_admin 
        WHERE status = 'read' 
        AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->bind_param("i", $days);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Get time ago format (e.g., "2 minutes ago")
 */
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y g:i A', $timestamp);
    }
}

/**
 * Get notification type and icon from message (auto-detect)
 */
function getNotificationIcon($message) {
    $message_lower = strtolower($message);
    
    if (strpos($message_lower, 'booking') !== false || strpos($message_lower, 'schedule') !== false) {
        return ['type' => 'new_schedule', 'icon' => 'fa-calendar-plus'];
    } elseif (strpos($message_lower, 'complaint') !== false) {
        return ['type' => 'new_complaint', 'icon' => 'fa-exclamation-circle'];
    } elseif (strpos($message_lower, 'payment') !== false) {
        return ['type' => 'payment_received', 'icon' => 'fa-money-bill-wave'];
    } elseif (strpos($message_lower, 'customer') !== false || strpos($message_lower, 'register') !== false) {
        return ['type' => 'new_customer', 'icon' => 'fa-user-plus'];
    } elseif (strpos($message_lower, 'feedback') !== false || strpos($message_lower, 'review') !== false) {
        return ['type' => 'new_feedback', 'icon' => 'fa-comment'];
    } else {
        return ['type' => 'notification', 'icon' => 'fa-bell'];
    }
}

/**
 * Get notification link based on type
 */
function getNotificationLink($booking_id, $message) {
    $message_lower = strtolower($message);
    
    if (strpos($message_lower, 'booking') !== false || strpos($message_lower, 'schedule') !== false) {
        return 'order_scheduling.php?id=' . $booking_id;
    } elseif (strpos($message_lower, 'complaint') !== false) {
        return 'complaints.php?id=' . $booking_id;
    } elseif (strpos($message_lower, 'payment') !== false) {
        return 'payments.php?id=' . $booking_id;
    } elseif (strpos($message_lower, 'customer') !== false) {
        return 'customer_database.php?id=' . $booking_id;
    } elseif (strpos($message_lower, 'feedback') !== false) {
        return 'feedback.php?id=' . $booking_id;
    } else {
        return 'dashboard.php';
    }
}
?>