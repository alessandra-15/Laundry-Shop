<?php
// update_payment_status.php
// AJAX endpoint — ginagamit ng payments.php para i-update ang payment_status
// at mag-send ng notification sa user.
//
// PAANO GAMITIN (sa payments.php JavaScript):
//   fetch('update_payment_status.php', {
//     method: 'POST',
//     headers: {'Content-Type': 'application/x-www-form-urlencoded'},
//     body: `transaction_id=${id}&payment_status=Paid`
//   }).then(r => r.json()).then(data => { ... });

session_start();
header('Content-Type: application/json');

include 'db_connect.php';
include_once 'user_notif_helpers.php';

// Admin session check
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$transaction_id = (int)($_POST['transaction_id'] ?? 0);
$new_status     = trim($_POST['payment_status'] ?? '');

$allowed = ['Paid', 'Pending', 'Unpaid'];
if ($transaction_id <= 0 || !in_array($new_status, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Fetch current transaction
$stmt = $conn->prepare("
    SELECT t.transaction_id, t.Customer_ID, t.payment, t.payment_status, s.service
    FROM `transaction` t
    LEFT JOIN schedule s ON t.Schedule_ID = s.Schedule_ID
    WHERE t.Transaction_ID = ?
    LIMIT 1
");
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Transaction not found']);
    exit;
}

$old_status = $row['payment_status'];

// Update payment status
$upd = $conn->prepare("UPDATE `transaction` SET payment_status = ? WHERE Transaction_ID = ?");
$upd->bind_param("si", $new_status, $transaction_id);
$ok = $upd->execute();
$upd->close();

if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Update failed: ' . $conn->error]);
    exit;
}

// ✅ Notify user only when status changes TO Paid
if ($new_status === 'Paid' && $old_status !== 'Paid') {
    notifyUserPaymentConfirmed($conn, $transaction_id, (float)$row['payment']);
}

echo json_encode([
    'success'        => true,
    'transaction_id' => $transaction_id,
    'old_status'     => $old_status,
    'new_status'     => $new_status,
    'message'        => "Payment status updated to {$new_status}."
]);

$conn->close();
?>