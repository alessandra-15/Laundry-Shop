<?php
$page_title = "GCash Payment - Mang TV Laundry Shop";
include 'db_connect.php';
session_start();

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];
$booking_id = isset($_REQUEST['booking_id']) ? (int)$_REQUEST['booking_id'] : 0;
$amount = isset($_REQUEST['amount']) ? floatval($_REQUEST['amount']) : 0;
$ref = isset($_REQUEST['ref']) ? $_REQUEST['ref'] : uniqid('gcash_');

// Fetch booking and verify ownership
$stmt = $conn->prepare("SELECT *, id AS booking_id FROM booking_online WHERE id = ? AND customer_id = ?");
$stmt->bind_param("ii", $booking_id, $customer_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();

if (!$booking) {
    header("Location: userdashboard.php");
    exit();
}

$sim_error = '';
$sim_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simulate payment confirmation with uploaded receipt/proof
    $posted_ref = $_POST['reference_number'] ?? $ref;
    $posted_amount = isset($_POST['amount']) ? floatval($_POST['amount']) : $amount;

    // Handle uploaded proof
    $payment_proof = '';
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] == 0) {
        $target_dir = __DIR__ . "/uploads/payment_proofs/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

        $file_extension = strtolower(pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION));
        $new_filename = uniqid('payment_') . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;

        $allowed_types = ['jpg','jpeg','png','pdf'];
        if (!in_array($file_extension, $allowed_types)) {
            $sim_error = 'Invalid file type. Allowed: JPG, PNG, PDF.';
        } elseif ($_FILES['payment_proof']['size'] > 5 * 1024 * 1024) {
            $sim_error = 'File too large (max 5MB).';
        } elseif (!move_uploaded_file($_FILES['payment_proof']['tmp_name'], $target_file)) {
            $sim_error = 'Failed to save uploaded file.';
        } else {
            // store relative path
            $payment_proof = 'uploads/payment_proofs/' . $new_filename;
        }
    } else {
        $sim_error = 'Please upload your payment receipt/proof.';
    }

    if (empty($sim_error)) {
        // Insert simulated payment record including uploaded proof
        $stmt = $conn->prepare("INSERT INTO payments_online (booking_id, amount, payment_method, reference_number, payment_proof, payment_status) VALUES (?, ?, 'GCash', ?, ?, 'Paid')");
        $stmt->bind_param("idss", $booking_id, $posted_amount, $posted_ref, $payment_proof);
        if ($stmt->execute()) {
            // Update booking status to Paid
            $u = $conn->prepare("UPDATE booking_online SET payment_status = 'Paid' WHERE id = ?");
            $u->bind_param("i", $booking_id);
            $u->execute();

            $sim_success = 'Payment confirmed and recorded as PAID. Redirecting to dashboard...';
            header("refresh:2;url=userdashboard.php");
            exit();
        } else {
            $sim_error = 'Error recording simulated payment: ' . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sim-container { max-width:720px; margin:40px auto; }
        .qr { text-align:center; }
        .card-amount { font-size:1.5rem; font-weight:700; }
    </style>
</head>
<body>
    <div class="container sim-container">
        <div class="card p-4">
            <h3 class="mb-3">Simulated GCash Payment</h3>
            <?php if ($sim_error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($sim_error); ?></div>
            <?php endif; ?>
            <?php if ($sim_success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($sim_success); ?></div>
            <?php endif; ?>

            <p><strong>Booking:</strong> #<?php echo $booking['booking_id']; ?> — <?php echo htmlspecialchars($booking['service']); ?></p>
            <p class="card-amount">Amount: ₱<?php echo number_format($amount,2); ?></p>

            <div class="qr mb-3">
                <?php
                    $qrText = "PAYTO:09676051714|AMOUNT:" . number_format($amount,2) . "|REF:" . urlencode($ref);
                    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrText);
                ?>
                <img src="<?php echo $qrUrl; ?>" alt="QR Code" class="img-fluid">
                <p class="small text-muted mt-2">Scan this QR in a real GCash app (simulated)</p>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Reference Number</label>
                    <input type="text" name="reference_number" value="<?php echo htmlspecialchars($ref); ?>" class="form-control" required>
                </div>
                <input type="hidden" name="amount" value="<?php echo htmlspecialchars($amount); ?>">

                <div class="mb-3">
                    <label class="form-label">Upload Receipt / Payment Proof</label>
                    <input type="file" name="payment_proof" accept="image/*,.pdf" class="form-control" required>
                    <div class="form-text">Upload screenshot or PDF of the payment confirmation (max 5MB).</div>
                </div>

                <div class="mb-3">
                    <button type="button" class="btn btn-outline-secondary me-2" onclick="window.print()">Print / Download Receipt</button>
                    <button type="submit" class="btn btn-success">Confirm & Record Payment</button>
                    <a href="payment.php?booking_id=<?php echo $booking_id; ?>" class="btn btn-link">Back</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
