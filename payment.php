<?php
$page_title = "Payment - Mang TV Laundry Shop";
include 'db_connect.php';
$session_started = session_start();

// Require authentication for all requests
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

// Determine booking_id: prefer POST (when submitting), otherwise GET (when loading page)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = isset($_POST['booking_id']) ? $_POST['booking_id'] : (isset($_GET['booking_id']) ? $_GET['booking_id'] : null);
} else {
    $booking_id = isset($_GET['booking_id']) ? $_GET['booking_id'] : null;
}

if (empty($booking_id)) {
    header("Location: userdashboard.php");
    exit();
}
$customer_id = $_SESSION['customer_id'];

$stmt = $conn->prepare("SELECT *, id AS booking_id FROM booking_online WHERE id = ? AND customer_id = ?");
$stmt->bind_param("ii", $booking_id, $customer_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();

if (!$booking) {
    header("Location: userdashboard.php");
    exit();
}

$payment_error = '';
$payment_success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $payment_method = $_POST['payment_method'] ?? '';
    
    // Only GCash is accepted for online booking
    if ($payment_method !== 'GCash') {
        $payment_error = "Invalid payment method. Online bookings must use GCash.";
    } else {
        $reference_number = $_POST['reference_number'] ?? '';
        $amount = $booking['total_amount'];

        if (empty($reference_number)) {
            $payment_error = "Reference number is required.";
        }

        // Handle file upload for GCash receipt
        $payment_proof = '';
        if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] != 0) {
            $payment_error = "Please upload your GCash payment receipt.";
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
                $payment_error = "Sorry, only JPG, JPEG, PNG & PDF files are allowed.";
            } else if ($_FILES["payment_proof"]["size"] > 5000000) {
                $payment_error = "Sorry, your file is too large. Maximum 5MB.";
            } else if (move_uploaded_file($_FILES["payment_proof"]["tmp_name"], $target_file)) {
                $payment_proof = $new_filename;
            } else {
                $payment_error = "Sorry, there was an error uploading your file.";
            }
        }

        if (empty($payment_error)) {
            $payment_status = 'Paid';

            // Determine discount
            $discount = 0.0;
            if (isset($booking['discount']) && is_numeric($booking['discount']) && $booking['discount'] > 0) {
                $discount = (float) $booking['discount'];
            } else {
                $custStmt = $conn->prepare("SELECT discount_rate FROM customer_info WHERE Customer_ID = ?");
                $custStmt->bind_param("i", $customer_id);
                $custStmt->execute();
                $custRes = $custStmt->get_result();
                $custRow = $custRes->fetch_assoc();
                $custStmt->close();
                if ($custRow && isset($custRow['discount_rate']) && is_numeric($custRow['discount_rate']) && $custRow['discount_rate'] > 0) {
                    $discount = round((float)$booking['total_amount'] * (float)$custRow['discount_rate'], 2);
                }
            }

            // Insert payment
            $stmt = $conn->prepare("INSERT INTO payments_online (booking_id, amount, payment_method, reference_number, payment_proof, payment_status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("idssss", $booking_id, $amount, $payment_method, $reference_number, $payment_proof, $payment_status);
            
            if ($stmt->execute()) {
                // Update booking
                $update = $conn->prepare("UPDATE booking_online SET payment_status = 'Paid', discount = ? WHERE id = ?");
                $update->bind_param("di", $discount, $booking_id);
                $update->execute();
                $update->close();
                $stmt->close();

                $payment_success = "GCash payment submitted successfully! Redirecting...";
                header("refresh:2;url=userdashboard.php");
            } else {
                $payment_error = "Error submitting payment: " . $stmt->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GCash Payment - Mang TV Laundry Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --dark-blue: #00537A;
            --yellow: #FFD35B;
            --light-blue: #A8E8F9;
            --bg-light: #F8FBFF;
            --text-dark: #2c3e50;
            --gcash-blue: #007DFF;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--text-dark);
            min-height: 100vh;
            padding: 20px 0;
        }

        .payment-container {
            max-width: 700px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .payment-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .payment-header {
            background: linear-gradient(135deg, var(--gcash-blue) 0%, #0056cc 100%);
            padding: 2rem;
            text-align: center;
            position: relative;
        }

        .gcash-logo-header {
            width: 120px;
            height: auto;
            margin-bottom: 1rem;
            filter: brightness(0) invert(1);
        }

        .payment-header h2 {
            color: white;
            font-weight: 700;
            font-size: 1.5rem;
            margin: 0;
        }

        .back-btn {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(-3px);
            color: white;
        }

        .payment-body {
            padding: 2rem;
        }

        .alert-custom {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fff5f5 0%, #ffe5e5 100%);
            color: #c53030;
            border-left: 4px solid #c53030;
        }

        .alert-success {
            background: linear-gradient(135deg, #f0fff4 0%, #c6f6d5 100%);
            color: #22543d;
            border-left: 4px solid #38a169;
        }

        .booking-summary {
            background: linear-gradient(135deg, #f8f9ff 0%, #e8eeff 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 2px solid rgba(0, 125, 255, 0.1);
        }

        .booking-summary h5 {
            color: var(--gcash-blue);
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(0, 125, 255, 0.1);
        }

        .summary-row:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--gcash-blue);
            padding-top: 1rem;
            margin-top: 0.5rem;
            border-top: 2px solid rgba(0, 125, 255, 0.2);
        }

        .qr-section {
            background: white;
            border: 3px solid var(--gcash-blue);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            margin: 2rem 0;
            box-shadow: 0 5px 20px rgba(0, 125, 255, 0.15);
        }

        .qr-section h6 {
            color: var(--gcash-blue);
            font-weight: 700;
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
        }

        .qr-code-wrapper {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .qr-code {
            max-width: 280px;
            width: 100%;
            height: auto;
            border-radius: 10px;
        }

        .gcash-number {
            background: linear-gradient(135deg, var(--gcash-blue) 0%, #0056cc 100%);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            margin: 1.5rem 0;
            font-weight: 700;
            font-size: 1.3rem;
            letter-spacing: 1px;
        }

        .reference-section {
            background: linear-gradient(135deg, #fff9e6 0%, #ffe6a3 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border: 2px solid #ffd700;
        }

        .reference-label {
            color: #856404;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
        }

        .reference-number {
            background: white;
            border: 3px solid #ffd700;
            border-radius: 12px;
            padding: 1rem;
            font-weight: 800;
            font-size: 1.4rem;
            color: #856404;
            letter-spacing: 2px;
            text-align: center;
            font-family: 'Courier New', monospace;
        }

        .instructions-card {
            background: linear-gradient(135deg, #e8f4fd 0%, #d4ebf7 100%);
            border-left: 5px solid var(--gcash-blue);
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .instructions-card h6 {
            color: var(--gcash-blue);
            font-weight: 700;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .instructions-card ol {
            margin-bottom: 0;
            padding-left: 1.5rem;
        }

        .instructions-card li {
            margin-bottom: 0.75rem;
            color: #2c5282;
            font-weight: 500;
            line-height: 1.6;
        }

        .instructions-card li strong {
            color: var(--gcash-blue);
        }

        .upload-section {
            background: linear-gradient(135deg, #fff5f5 0%, #ffe5e5 100%);
            border: 3px dashed #ff6b6b;
            border-radius: 20px;
            padding: 2.5rem;
            text-align: center;
            margin: 2rem 0;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }

        .upload-section:hover {
            background: linear-gradient(135deg, #fff0f0 0%, #ffd5d5 100%);
            border-color: #ff5252;
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(255, 107, 107, 0.2);
        }

        .upload-section i {
            font-size: 3.5rem;
            color: #ff6b6b;
            margin-bottom: 1rem;
        }

        .upload-section h5 {
            color: #c53030;
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-size: 1.3rem;
        }

        .upload-section .required-badge {
            background: #ff6b6b;
            color: white;
            padding: 0.3rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            display: inline-block;
            margin-bottom: 1rem;
        }

        .upload-section p {
            color: #744210;
            font-size: 0.95rem;
            margin: 0;
        }

        .file-input {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            opacity: 0;
            cursor: pointer;
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--gcash-blue) 0%, #0056cc 100%);
            color: white;
            padding: 1.2rem 2rem;
            border: none;
            border-radius: 15px;
            font-weight: 700;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s;
            box-shadow: 0 5px 20px rgba(0, 125, 255, 0.3);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 125, 255, 0.4);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .security-note {
            text-align: center;
            color: #718096;
            font-size: 0.85rem;
            margin-top: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .security-note i {
            color: #48bb78;
        }

        @media (max-width: 768px) {
            .payment-body {
                padding: 1.5rem;
            }

            .back-btn {
                position: static;
                margin-bottom: 1rem;
            }

            .payment-header {
                padding: 1.5rem;
            }

            .qr-code {
                max-width: 220px;
            }

            .reference-number {
                font-size: 1.1rem;
            }

            .gcash-number {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="payment-container">
            <div class="payment-card">
                <div class="payment-header">
                    <a href="booking.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <img src="https://www.gcash.com/wp-content/uploads/2019/05/GCash-Logo-PNG-1.png" alt="GCash" class="gcash-logo-header">
                    <h2>Secure Online Payment</h2>
                </div>

                <div class="payment-body">
                    <?php if (!empty($payment_error)): ?>
                        <div class="alert alert-danger alert-custom">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $payment_error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($payment_success)): ?>
                        <div class="alert alert-success alert-custom">
                            <i class="fas fa-check-circle"></i> <?php echo $payment_success; ?>
                        </div>
                    <?php endif; ?>

                    <div class="booking-summary">
                        <h5><i class="fas fa-receipt"></i> Booking Summary</h5>
                        <div class="summary-row">
                            <span>Booking ID:</span>
                            <strong>#<?php echo $booking['booking_id']; ?></strong>
                        </div>
                        <div class="summary-row">
                            <span>Services:</span>
                            <strong><?php echo $booking['service']; ?></strong>
                        </div>
                        <?php if ($booking['addons']): ?>
                        <div class="summary-row">
                            <span>Add-ons:</span>
                            <strong><?php echo $booking['addons']; ?></strong>
                        </div>
                        <?php endif; ?>
                        <div class="summary-row">
                            <span>Total Amount:</span>
                            <strong>₱<?php echo number_format($booking['total_amount'], 2); ?></strong>
                        </div>
                    </div>

                    <form method="POST" enctype="multipart/form-data" id="gcashPaymentForm">
                        <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                        <input type="hidden" name="payment_method" value="GCash">
                        <input type="hidden" name="reference_number" id="referenceNumberInput">

                        <div class="qr-section">
                            <h6><i class="fas fa-qrcode"></i> Scan QR Code to Pay</h6>
                            <div class="qr-code-wrapper">
                                <div id="qrCodeContainer"></div>
                            </div>
                            <div class="gcash-number">
                                <i class="fas fa-mobile-alt"></i> 09676051714
                            </div>
                            <small style="color: #666;">GCash Merchant Number</small>
                        </div>

                        <div class="reference-section">
                            <div class="reference-label">
                                <i class="fas fa-hashtag"></i> Your Reference Number
                            </div>
                            <div class="reference-number" id="displayReference">
                                Generating...
                            </div>
                            <small style="color: #856404; display: block; text-align: center; margin-top: 0.5rem;">
                                <i class="fas fa-info-circle"></i> Enter this in the GCash "Add Reference" field
                            </small>
                        </div>

                        <div class="instructions-card">
                            <h6><i class="fas fa-list-ol"></i> Payment Instructions</h6>
                            <ol>
                                <li>Open your <strong>GCash app</strong></li>
                                <li><strong>Scan the QR code</strong> above or manually enter <strong>09676051714</strong></li>
                                <li>Enter the amount: <strong>₱<?php echo number_format($booking['total_amount'], 2); ?></strong></li>
                                <li>When prompted, <strong>add the reference number shown above</strong></li>
                                <li><strong>Complete the payment</strong> in your GCash app</li>
                                <li>Take a <strong>screenshot</strong> of the payment confirmation</li>
                                <li><strong>Upload the screenshot</strong> below to confirm your payment</li>
                            </ol>
                        </div>

                        <div class="upload-section" onclick="document.getElementById('paymentProof').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span class="required-badge">REQUIRED</span>
                            <h5>Upload Payment Receipt</h5>
                            <p>Click here to upload your GCash transaction screenshot</p>
                            <p style="margin-top: 0.5rem; font-size: 0.85rem; color: #a0aec0;">
                                JPG, PNG or PDF • Max 5MB
                            </p>
                            <input type="file" id="paymentProof" name="payment_proof" class="file-input" accept="image/jpeg,image/png,image/jpg,application/pdf" required>
                            <div id="fileName" style="margin-top: 1rem; color: #38a169; font-weight: 600;"></div>
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="fas fa-check-circle"></i> Submit Payment
                        </button>

                        <div class="security-note">
                            <i class="fas fa-lock"></i> Your payment is secure and encrypted
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Generate GCash-style reference number
        function generateGCashReference() {
            const part1 = String(Math.floor(Math.random() * 9000) + 1000);
            const part2 = String(Math.floor(Math.random() * 900) + 100);
            const part3 = String(Math.floor(Math.random() * 900000) + 100000);
            return ${part1} ${part2} ${part3};
        }

        // Generate and display reference number
        const referenceNumber = generateGCashReference();
        document.getElementById('displayReference').textContent = referenceNumber;
        document.getElementById('referenceNumberInput').value = referenceNumber;

        // Generate QR Code
        const bookingAmount = <?php echo json_encode($booking['total_amount']); ?>;
        const qrData = PAYTO:09676051714|AMOUNT:${bookingAmount}|REF:${referenceNumber};
        const qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=" + encodeURIComponent(qrData);
        
        const qrImg = document.createElement('img');
        qrImg.src = qrUrl;
        qrImg.alt = 'GCash QR Code';
        qrImg.className = 'qr-code';
        document.getElementById('qrCodeContainer').appendChild(qrImg);

        // File upload handler
        document.getElementById('paymentProof').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            const fileDisplay = document.getElementById('fileName');
            if (fileName) {
                fileDisplay.innerHTML = <i class="fas fa-file-image"></i> ${fileName};
            } else {
                fileDisplay.innerHTML = '';
            }
        });

        // Form validation
        document.getElementById('gcashPaymentForm').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('paymentProof');
            if (!fileInput.files || !fileInput.files[0]) {
                e.preventDefault();
                alert('Please upload your GCash payment receipt before submitting.');
                return false;
            }
        });
    </script>
</body>
</html>