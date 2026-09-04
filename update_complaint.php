<?php
// update_complaint.php — Handle complaint status update via AJAX
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

// Test if db_connect.php exists
if (!file_exists('db_connect.php')) {
    echo json_encode(['success' => false, 'message' => 'db_connect.php not found. Check file path.']);
    exit;
}

include 'db_connect.php';
include_once 'user_notif_helpers.php';

// Test if $conn is valid
if (!$conn || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed: ' . ($conn->connect_error ?? 'unknown error')]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$complaint_id = intval($_POST['complaint_id'] ?? 0);
$status       = trim($_POST['status'] ?? '');
$remarks      = trim($_POST['remarks'] ?? '');
$handled_by   = trim($_POST['handled_by'] ?? '');

if (!$complaint_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid complaint ID: ' . $_POST['complaint_id']]);
    exit;
}

$allowed_statuses = ['Pending', 'In Progress', 'Resolved'];
if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status: ' . $status]);
    exit;
}

if ($status === 'Resolved') {
    $stmt = $conn->prepare("
        UPDATE complaints
        SET status = ?, remarks = ?, handled_by = ?, date_resolved = CURDATE()
        WHERE complaint_id = ?
    ");
} else {
    $stmt = $conn->prepare("
        UPDATE complaints
        SET status = ?, remarks = ?, handled_by = ?, date_resolved = NULL
        WHERE complaint_id = ?
    ");
}

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param('sssi', $status, $remarks, $handled_by, $complaint_id);

if ($stmt->execute()) {
    $result = $conn->query("
        SELECT complaint_id, customer_id, status, remarks, handled_by, date_resolved
        FROM complaints
        WHERE complaint_id = $complaint_id
    ");

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Select failed: ' . $conn->error]);
        exit;
    }

    $row = $result->fetch_assoc();

    // ✅ Notify the user about complaint status update
    if (in_array($status, ['In Progress', 'Resolved'])) {
        notifyUserComplaintUpdate($conn, $complaint_id, (int)$row['customer_id'], $status, $remarks);
    }

    echo json_encode([
        'success'       => true,
        'message'       => 'Complaint updated successfully.',
        'complaint_id'  => $row['complaint_id'],
        'status'        => $row['status'],
        'remarks'       => $row['remarks'],
        'handled_by'    => $row['handled_by'],
        'date_resolved' => $row['date_resolved']
            ? date('M d, Y', strtotime($row['date_resolved']))
            : null,
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Execute failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();