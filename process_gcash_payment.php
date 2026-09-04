<?php
include 'db_connect.php';
session_start();

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];
$booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
$reference_number = isset($_POST['reference_number']) ? trim($_POST['reference_number']) : '';
$payment_method = 'GCash';
$payment_error = '';

// DEBUG: Log everything received
error_log("=== GCash Payment Processing ===");
error_log("POST: " . json_encode($_POST));
error_log("FILES: " . json_encode(array_map(function($f) { return ['name' => $f['name'], 'error' => $f['error'], 'size' => $f['size']]; }, $_FILES)));
error_log("booking_id: $booking_id, reference_number: $reference_number, customer_id: $customer_id");

if ($booking_id <= 0) {
    $payment_error = "Invalid booking ID: $booking_id";
} elseif (empty($reference_number)) {
    $payment_error = "Reference number is empty";
} else {
    // Verify booking belongs to customer
    $stmt = $conn->prepare("SELECT * FROM booking_online WHERE id = ? AND customer_id = ?");
    $stmt->bind_param("ii", $booking_id, $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();
    $stmt->close();

    if (!$booking) {
        $payment_error = "Booking not found for ID: $booking_id, Customer: $customer_id";
    } else {
        // Handle file upload
        $payment_proof = '';
        if (!isset($_FILES['payment_proof'])) {
            $payment_error = "No file uploaded - _FILES['payment_proof'] not set";
        } elseif ($_FILES['payment_proof']['error'] != 0) {
            $payment_error = "File upload error code: " . $_FILES['payment_proof']['error'];
        } else {
            $target_dir = "uploads/payment_proofs/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $file_extension = strtolower(pathinfo($_FILES["payment_proof"]["name"], PATHINFO_EXTENSION));
            $new_filename = uniqid('gcash_') . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;

            $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'];
            
            if (!in_array($file_extension, $allowed_types)) {
                $payment_error = "Invalid file type: $file_extension (allowed: jpg, jpeg, png, pdf)";
            } elseif ($_FILES["payment_proof"]["size"] > 5000000) {
                $payment_error = "File too large: " . ($_FILES["payment_proof"]["size"] / 1024 / 1024) . "MB (max 5MB)";
            } elseif (move_uploaded_file($_FILES["payment_proof"]["tmp_name"], $target_file)) {
                $payment_proof = $new_filename;
                error_log("File uploaded successfully: $payment_proof");
            } else {
                $payment_error = "Failed to move uploaded file to $target_file";
            }
        }

        if (empty($payment_error)) {
            $amount = $booking['total_amount'];
            $payment_status = 'Paid';

            // Insert payment
            $stmt = $conn->prepare("INSERT INTO payments_online (booking_id, amount, payment_method, reference_number, payment_proof, payment_status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("idssss", $booking_id, $amount, $payment_method, $reference_number, $payment_proof, $payment_status);
            
            if ($stmt->execute()) {
                error_log("Payment inserted successfully for booking: $booking_id");
                
                // Update booking status
                $update = $conn->prepare("UPDATE booking_online SET payment_status = 'Paid' WHERE id = ?");
                $update->bind_param("i", $booking_id);
                $update->execute();
                $update->close();
                $stmt->close();

                error_log("Booking updated to Paid status");
                
                // Redirect to dashboard
                header("Location: userdashboard.php");
                exit();
            } else {
                $payment_error = "Database error: " . $stmt->error;
                error_log("Insert failed: " . $stmt->error);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GCash Payment Error</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-danger">
            <h4>Payment Processing Error</h4>
            <p><?php echo htmlspecialchars($payment_error); ?></p>
            <hr>
            <p><strong>Debug Info:</strong></p>
            <ul>
                <li>Booking ID: <?php echo $booking_id; ?></li>
                <li>Reference: <?php echo $reference_number; ?></li>
                <li>Customer ID: <?php echo $customer_id; ?></li>
                <li>Check browser console and PHP error log for more details</li>
            </ul>
            <a href="javascript:history.back()" class="btn btn-primary">Go Back</a>
        </div>
    </div>
</body>
</html>
