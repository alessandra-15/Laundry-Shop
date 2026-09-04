<?php
// notification_helpers.php - Helper functions for notifications_user

// Configuration: Socket server endpoint used to push real-time events.
// Update this to point to your running Socket server (Node + Socket.IO) notify endpoint.
if (!defined('SOCKET_SERVER_NOTIFY_URL')) {
    define('SOCKET_SERVER_NOTIFY_URL', 'http://localhost:3000/notify');
}

// Toggle emitting to socket server (set to false if you don't have the socket server running)
if (!defined('EMIT_NOTIFICATIONS')) {
    define('EMIT_NOTIFICATIONS', true);
}

/**
 * Create a new notification
 * Returns inserted notification ID on success, false on failure.
 * Also emits the notification to socket server (if enabled).
 */
function createNotification($conn, $booking_id, $user_id, $message, $status = 'sent') {
    $stmt = $conn->prepare("
        INSERT INTO notifications_user (booking_id, user_id, message, status, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("iiss", $booking_id, $user_id, $message, $status);
    $result = $stmt->execute();
    $insert_id = $stmt->insert_id ?? 0;
    $stmt->close();

    if ($result && $insert_id > 0) {
        // Prepare payload for emission
        $payload = [
            'notif_id' => (int)$insert_id,
            'booking_id' => (int)$booking_id,
            'user_id' => (int)$user_id,
            'message' => $message,
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s')
        ];
        if (EMIT_NOTIFICATIONS) {
            // Emit as 'new_notification' so clients can listen for that event
            emitToSocket('new_notification', $payload);
        }
        return $insert_id;
    }
    return false;
}

/**
 * Get unread notification count
 */
function getUnreadCount($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM notifications_user WHERE status = 'sent'");
    if (!$result) return 0;
    $row = $result->fetch_assoc();
    return (int)($row['count'] ?? 0);
}

/**
 * Get recent notifications
 */
function getRecentNotifications($conn, $limit = 10, $unread_only = false) {
    $where = $unread_only ? "WHERE status = 'pending'" : "";
    $limit = (int)$limit;
    $query = "
        SELECT notif_id, booking_id, user_id, message, status, created_at
        FROM notifications_user
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
    $stmt = $conn->prepare("UPDATE notifications_user SET status = 'read' WHERE notif_id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("i", $notif_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Mark all notifications as read
 */
function markAllAsRead($conn) {
    return $conn->query("UPDATE notifications_user SET status = 'read' WHERE status = 'sent'");
}

/**
 * Delete old read notifications (cleanup - optional)
 */
function deleteOldNotifications($conn, $days = 30) {
    $stmt = $conn->prepare("
        DELETE FROM notifications_user
        WHERE status = 'read'
        AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    if (!$stmt) return false;
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
        return 'order_scheduling.php?id=' . intval($booking_id);
    } elseif (strpos($message_lower, 'complaint') !== false) {
        return 'complaints.php?id=' . intval($booking_id);
    } elseif (strpos($message_lower, 'payment') !== false) {
        return 'payments.php?id=' . intval($booking_id);
    } elseif (strpos($message_lower, 'customer') !== false) {
        return 'customer_database.php?id=' . intval($booking_id);
    } elseif (strpos($message_lower, 'feedback') !== false) {
        return 'feedback.php?id=' . intval($booking_id);
    } else {
        return 'dashboard.php';
    }
}

/**
 * Emit a generic notification status update to socket server (optional)
 * $notif_id may be null for bulk operations.
 */
function emitNotificationStatusUpdate($notif_id = null, $action = 'read') {
    if (!EMIT_NOTIFICATIONS) return false;
    $payload = [
        'action' => $action,
        'notif_id' => $notif_id
    ];
    return emitToSocket('notification_update', $payload);
}

/**
 * Emit payload to socket server notify endpoint (HTTP POST)
 * - $eventType is the name of the socket event to emit (e.g., 'new_notification')
 * - $data is an array that will be sent as payload.data
 */
function emitToSocket($eventType, $data) {
    if (!EMIT_NOTIFICATIONS) return false;
    $payload = [
        'type' => $eventType,
        'data' => $data
    ];
    $ch = curl_init(SOCKET_SERVER_NOTIFY_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false && $err) {
        // Log error if you have a logger; otherwise ignore silently to avoid breaking flow
        return false;
    }
    return true;
}
?>