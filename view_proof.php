<?php
session_start();
include 'db_connect.php';

if (!isset($_GET['ref']) || !isset($_SESSION['customer_id'])) {
    http_response_code(400);
    echo 'Bad request';
    exit();
}

$ref = $_GET['ref'];
$customer_id = $_SESSION['customer_id'];

// Find payment and booking ownership
$customer_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

$stmt = $conn->prepare("
    SELECT p.payment_proof, p.payment_method, b.customer_id 
    FROM payments_online p 
    JOIN booking_online b ON p.booking_id = b.id 
    WHERE p.reference_number = ? 
    AND b.customer_name = ?
    LIMIT 1
");

$stmt->bind_param('ss', $ref, $customer_name);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();
$stmt->close();

// Log access attempt for security audit
if ($payment) {
    error_log("Payment proof accessed: ref={$ref}, user={$customer_name}, status=success");
} else {
    error_log("Payment proof access denied: ref={$ref}, user={$customer_name}, status=not_found");
}

if (!$payment) {
    http_response_code(404);
    echo 'Not found';
    exit();
}

// Ensure the logged in user owns this booking (or adjust if admin access needed)
// Additional validation already done in SQL with customer_name check
// But we'll keep ID check as a secondary validation
if ((int)$payment['customer_id'] !== (int)$customer_id) {
    error_log("Payment proof access denied: ref={$ref}, user={$customer_name}, status=forbidden");
    http_response_code(403);
    echo 'Forbidden';
    exit();
}

$filename = $payment['payment_proof'];
if (empty($filename)) {
    http_response_code(404);
    echo 'No proof available';
    exit();
}

$filepath = __DIR__ . '/uploads/payment_proofs/' . $filename;
if (!file_exists($filepath)) {
    http_response_code(404);
    echo 'File not found';
    exit();
}

// Validate file type
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$allowed_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'pdf' => 'application/pdf'
];

if (!array_key_exists($ext, $allowed_types)) {
    error_log("Payment proof invalid type: ref={$ref}, user={$customer_name}, file={$filename}");
    http_response_code(400);
    echo 'Invalid file type';
    exit();
}

$mime = $allowed_types[$ext];

// Serve file
header('Content-Type: ' . $mime);
// inline for viewing in-browser
header('Content-Disposition: inline; filename="' . basename($filename) . '"');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit();
?>