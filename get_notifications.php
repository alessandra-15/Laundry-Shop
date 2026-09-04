<?php
// get_notifications.php (ADMIN SIDE)
// AJAX endpoint para sa admin — nagre-read mula sa notifications_admin table.
//
// STATUS ENUM: enum('sent','pending')
//   'pending' = unread ng admin (bagong notif)
//   'sent'    = na-read na ng admin

session_start();
header('Content-Type: application/json');

include 'db_connect.php';

// Auth check — ginagamit ng admin login: $_SESSION['Admin_ID']
if (!isset($_SESSION['Admin_ID']) && !isset($_SESSION['login_success']) && !isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$action = $_REQUEST['action'] ?? '';

// ── Helpers ───────────────────────────────────────────────────────────────────

function adminGetUnreadCount($conn) {
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM notifications_admin WHERE status = 'pending'");
    if (!$result) return 0;
    return (int)($result->fetch_assoc()['cnt'] ?? 0);
}

function adminGetRecentNotifications($conn, $limit = 20, $unread_only = false) {
    $limit = (int)$limit;
    $where = $unread_only ? "WHERE status = 'pending'" : "";
    $sql = "SELECT notif_id, booking_id, user_id, message, status, created_at
            FROM notifications_admin
            {$where}
            ORDER BY created_at DESC
            LIMIT {$limit}";
    return $conn->query($sql);
}

function adminMarkAsRead($conn, $notif_id) {
    $stmt = $conn->prepare("UPDATE notifications_admin SET status = 'sent' WHERE notif_id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("i", $notif_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function adminMarkAllAsRead($conn) {
    return $conn->query("UPDATE notifications_admin SET status = 'sent' WHERE status = 'pending'");
}

function adminResolveNotifMeta($message) {
    $msg = strtolower($message);
    if (strpos($msg, 'feedback') !== false || strpos($msg, 'rating') !== false)
        return ['type' => 'new_feedback',  'icon' => 'fas fa-star',               'title' => 'New Feedback'];
    if (strpos($msg, 'complaint') !== false)
        return ['type' => 'new_complaint', 'icon' => 'fas fa-exclamation-circle',  'title' => 'New Complaint'];
    if (strpos($msg, 'payment') !== false || strpos($msg, 'paid') !== false)
        return ['type' => 'payment',       'icon' => 'fas fa-money-bill-wave',     'title' => 'Payment Received'];
    if (strpos($msg, 'registered') !== false || strpos($msg, 'customer') !== false)
        return ['type' => 'new_customer',  'icon' => 'fas fa-user-plus',           'title' => 'New Customer'];
    if (strpos($msg, 'booking') !== false || strpos($msg, 'scheduled') !== false)
        return ['type' => 'new_booking',   'icon' => 'fas fa-calendar-plus',       'title' => 'New Booking'];
    return     ['type' => 'notification',  'icon' => 'fas fa-bell',                'title' => 'Notification'];
}

function adminGetNotifLink($booking_id, $message) {
    $msg = strtolower($message);
    if (strpos($msg, 'feedback') !== false || strpos($msg, 'rating') !== false)
        return 'feedback_management.php' . ($booking_id ? '?id=' . (int)$booking_id : '');
    if (strpos($msg, 'complaint') !== false)
        return 'complaints.php';
    if (strpos($msg, 'payment') !== false || strpos($msg, 'paid') !== false)
        return 'payments.php' . ($booking_id ? '?id=' . (int)$booking_id : '');
    if (strpos($msg, 'registered') !== false || strpos($msg, 'customer') !== false)
        return 'customer_database.php';
    if (strpos($msg, 'booking') !== false || strpos($msg, 'scheduled') !== false)
        return 'order_scheduling.php' . ($booking_id ? '?id=' . (int)$booking_id : '');
    return 'dashboard.php';
}

function adminTimeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   { $m = floor($diff / 60);   return $m . ' min'  . ($m > 1 ? 's' : '') . ' ago'; }
    if ($diff < 86400)  { $h = floor($diff / 3600);  return $h . ' hr'   . ($h > 1 ? 's' : '') . ' ago'; }
    if ($diff < 604800) { $d = floor($diff / 86400); return $d . ' day'  . ($d > 1 ? 's' : '') . ' ago'; }
    return date('M j, Y g:i A', strtotime($datetime));
}

// ── Router ────────────────────────────────────────────────────────────────────

switch ($action) {

    case 'get_count':
        echo json_encode(['count' => adminGetUnreadCount($conn)]);
        break;

    case 'get_notifications':
        $unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] == '1';
        $limit       = min((int)($_GET['limit'] ?? 20), 50);

        $result = adminGetRecentNotifications($conn, $limit, $unread_only);

        if ($result === false) {
            http_response_code(500);
            echo json_encode(['error' => 'DB query failed: ' . $conn->error]);
            break;
        }

        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $meta            = adminResolveNotifMeta($row['message']);
            $notifications[] = [
                'notification_id' => (int)$row['notif_id'],
                'type'            => $meta['type'],
                'icon'            => $meta['icon'],
                'title'           => $meta['title'],
                'message'         => $row['message'],
                'link'            => adminGetNotifLink($row['booking_id'], $row['message']),
                'is_read'         => $row['status'] === 'sent' ? 1 : 0,
                'created_at'      => $row['created_at'],
                'time_ago'        => adminTimeAgo($row['created_at']),
            ];
        }

        echo json_encode([
            'notifications' => $notifications,
            'unread_count'  => adminGetUnreadCount($conn),
        ]);
        break;

    case 'mark_read':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); echo json_encode(['error' => 'Method not allowed']); break;
        }
        $notif_id = (int)($_POST['notification_id'] ?? 0);
        if ($notif_id > 0) {
            echo json_encode(['success' => adminMarkAsRead($conn, $notif_id), 'unread_count' => adminGetUnreadCount($conn)]);
        } else {
            echo json_encode(['error' => 'Invalid notification ID']);
        }
        break;

    case 'mark_all_read':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); echo json_encode(['error' => 'Method not allowed']); break;
        }
        echo json_encode(['success' => (bool)adminMarkAllAsRead($conn), 'unread_count' => 0]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action: "' . htmlspecialchars($action) . '"']);
}

$conn->close();