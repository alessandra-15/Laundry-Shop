<?php
// get_notifications.php - AJAX endpoint for fetching notifications
session_start();
header('Content-Type: application/json');

include 'db_connect.php';
include 'notification_helpers.php';

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'get_count':
        $count = getUnreadCount($conn);
        echo json_encode(['count' => $count]);
        break;
        
    case 'get_notifications':
        $unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] == '1';
        $limit = (int)($_GET['limit'] ?? 15);
        
        $result = getRecentNotifications($conn, $limit, $unread_only);
        $notifications = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Auto-detect icon and type from message
                $iconData = getNotificationIcon($row['message']);
                
$notifications[] = [
    'notification_id' => (int)$row['notif_id'],
    'type'           => $iconData['type'],
    'icon'           => $iconData['icon'],
    'title'          => 'Notification',
    'message'        => $row['message'],
    'link'        => getNotificationLink($row['booking_id'], $row['message']), // optional kung ayaw mo ng booking link
    'link'           => '#', // or null kung wala
    'is_read'        => $row['status'] === 'read' ? 1 : 0,
    'created_at'     => $row['created_at'],
    'time_ago'       => timeAgo($row['created_at'])
];

            }
        }
        
        $count = getUnreadCount($conn);
        
        echo json_encode([
            'notifications' => $notifications,
            'unread_count' => $count
        ]);
        break;
        
    case 'mark_read':
        // Ensure method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }
        $notification_id = (int)($_POST['notification_id'] ?? 0);
        if ($notification_id > 0) {
            $success = markAsRead($conn, $notification_id);
            // Emit update to socket server (optional)
            emitNotificationStatusUpdate($notification_id, 'read');
            $count = getUnreadCount($conn);
            echo json_encode([
                'success' => $success,
                'unread_count' => $count
            ]);
        } else {
            echo json_encode(['error' => 'Invalid notification ID']);
        }
        break;
        
    case 'mark_all_read':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }
        $success = markAllAsRead($conn);
        // Emit update to socket server (optional)
        emitNotificationStatusUpdate(null, 'read_all');
        echo json_encode([
            'success' => $success,
            'unread_count' => 0
        ]);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}

$conn->close();
?>